<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly MarketplaceSettingsService $settings,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function addItem(User $buyer, int $listingId, int $quantity, ?int $listingVariantId = null): Cart
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Choose at least one item.']);
        }

        $listing = Listing::query()->publiclyVisible()->findOrFail($listingId);

        if ($listing->listing_type !== 'buy_now') {
            throw ValidationException::withMessages(['listing_id' => 'Auction items cannot be added to a cart.']);
        }

        $variant = $this->resolveVariant($listing, $listingVariantId);
        $availableQuantity = $variant?->availableQuantity() ?? ($listing->stock_quantity - $listing->reserved_quantity);

        if (! $listing->allow_backorders && $quantity > $availableQuantity) {
            throw ValidationException::withMessages(['quantity' => 'This quantity is no longer available.']);
        }

        $cart = Cart::query()->firstOrCreate(['buyer_id' => $buyer->id]);
        $item = $cart->items()->firstOrNew([
            'listing_id' => $listing->id,
            'selection_key' => $variant === null ? 'base' : $variant->combination_key,
        ]);
        $item->variant()->associate($variant);
        $item->quantity = $quantity;
        $item->save();

        return $cart->load('items.listing.sellerProfile');
    }

    /** @param array<string, string|null> $shippingAddress */
    public function checkout(User $buyer, string $paymentMethod, array $shippingAddress): CustomerOrder
    {
        return DB::transaction(function () use ($buyer, $paymentMethod, $shippingAddress): CustomerOrder {
            $cart = Cart::query()->where('buyer_id', $buyer->id)->lockForUpdate()->firstOrFail();
            $cartItems = $cart->items()->with(['listing.sellerProfile', 'variant.optionValues.option'])->lockForUpdate()->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $lockedListings = [];
            $lockedVariants = [];
            foreach ($cartItems as $cartItem) {
                $listing = Listing::query()->lockForUpdate()->findOrFail($cartItem->listing_id);

                if ($listing->listing_type !== 'buy_now' || $listing->status !== 'approved' || ! $listing->is_active) {
                    throw ValidationException::withMessages(['cart' => "{$listing->title} is no longer available to purchase."]);
                }

                $variant = $cartItem->listing_variant_id === null
                    ? null
                    : ListingVariant::query()->with('optionValues.option')->lockForUpdate()->find($cartItem->listing_variant_id);

                if ($listing->product_type === 'variant' && ($variant === null || $variant->listing_id !== $listing->id || ! $variant->is_active)) {
                    throw ValidationException::withMessages(['cart' => "Choose an available option for {$listing->title}."]);
                }

                $availableQuantity = $variant?->availableQuantity() ?? ($listing->stock_quantity - $listing->reserved_quantity);
                if (! $listing->allow_backorders && $availableQuantity < $cartItem->quantity) {
                    throw ValidationException::withMessages(['cart' => "{$listing->title} no longer has enough stock."]);
                }

                $lockedListings[$listing->id] = $listing;
                if ($variant !== null) {
                    $lockedVariants[$cartItem->id] = $variant;
                }
            }

            $subtotal = BigDecimal::zero();
            foreach ($cartItems as $cartItem) {
                $listing = $lockedListings[$cartItem->listing_id];
                $variant = $lockedVariants[$cartItem->id] ?? null;
                $subtotal = $subtotal->plus(BigDecimal::of($this->buyNowPrice($listing, $variant))->multipliedBy($cartItem->quantity));
            }

            if ($paymentMethod === 'cod' && $subtotal->isGreaterThan($this->settings->integer('checkout.cod_maximum_amount', 50000))) {
                throw ValidationException::withMessages(['payment_method' => 'Cash on delivery is not available for this order total.']);
            }

            $order = CustomerOrder::query()->create([
                'number' => $this->orderNumber('CM'),
                'buyer_id' => $buyer->id,
                'status' => $paymentMethod === 'cod' ? 'confirmed' : 'pending_payment',
                'subtotal' => (string) $subtotal,
                'total' => (string) $subtotal,
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cartItems->groupBy(fn (CartItem $item): int => $item->listing->seller_profile_id) as $sellerProfileId => $items) {
                $sellerSubtotal = BigDecimal::zero();
                foreach ($items as $item) {
                    $listing = $lockedListings[$item->listing_id];
                    $variant = $lockedVariants[$item->id] ?? null;
                    $sellerSubtotal = $sellerSubtotal->plus(BigDecimal::of($this->buyNowPrice($listing, $variant))->multipliedBy($item->quantity));
                }

                $sellerOrder = SellerOrder::query()->create([
                    'number' => $this->orderNumber('SO'),
                    'customer_order_id' => $order->id,
                    'seller_profile_id' => $sellerProfileId,
                    'status' => $paymentMethod === 'cod' ? 'paid' : 'pending_payment',
                    'subtotal' => (string) $sellerSubtotal,
                    'seller_earnings' => (string) $sellerSubtotal,
                ]);

                foreach ($items as $item) {
                    $listing = $lockedListings[$item->listing_id];
                    $variant = $lockedVariants[$item->id] ?? null;
                    $effectivePrice = $this->buyNowPrice($listing, $variant);
                    $lineTotal = BigDecimal::of($effectivePrice)->multipliedBy($item->quantity);
                    $commission = $lineTotal->multipliedBy((string) $listing->commission_percentage)->dividedBy(100, 2, RoundingMode::Down);
                    $sellerOrder->items()->create([
                        'listing_id' => $listing->id,
                        'listing_variant_id' => $variant?->id,
                        'title' => $listing->title,
                        'variant_sku' => $variant?->sku,
                        'variant_options' => $variant === null ? null : $this->variantOptions($variant),
                        'quantity' => $item->quantity,
                        'unit_price' => $effectivePrice,
                        'commission_percentage' => $listing->commission_percentage,
                        'commission_amount' => (string) $commission,
                        'total' => (string) $lineTotal,
                    ]);
                    $listing->increment('reserved_quantity', $item->quantity);
                    $variant?->increment('reserved_quantity', $item->quantity);
                }
            }

            $payment = Payment::query()->create([
                'customer_order_id' => $order->id,
                'method' => $paymentMethod,
                'status' => $paymentMethod === 'cod' ? 'pending_collection' : 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'amount' => (string) $subtotal,
            ]);
            $cart->items()->delete();
            $this->auditLogs->record($buyer, 'checkout.created', $order, after: $order->getAttributes());

            return $order->load(['sellerOrders.items', 'payments']);
        }, attempts: 3);
    }

    private function orderNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }

    private function resolveVariant(Listing $listing, ?int $listingVariantId): ?ListingVariant
    {
        if ($listing->product_type !== 'variant') {
            if ($listingVariantId !== null) {
                throw ValidationException::withMessages(['listing_variant_id' => 'This product does not use selectable options.']);
            }

            return null;
        }

        if ($listingVariantId === null) {
            throw ValidationException::withMessages(['listing_variant_id' => 'Choose all product options before adding this item.']);
        }

        $variant = ListingVariant::query()->whereBelongsTo($listing)->with('optionValues.option')->find($listingVariantId);

        if ($variant === null || ! $variant->is_active) {
            throw ValidationException::withMessages(['listing_variant_id' => 'The selected product option is unavailable.']);
        }

        return $variant;
    }

    private function buyNowPrice(Listing $listing, ?ListingVariant $variant): string
    {
        $price = $variant?->buyNowPrice() ?? $listing->buyNowPrice();

        if ($price === null) {
            throw ValidationException::withMessages(['cart' => "{$listing->title} does not have an available price."]);
        }

        return $price;
    }

    /** @return array<string, string> */
    private function variantOptions(ListingVariant $variant): array
    {
        return $variant->optionValues
            ->sortBy(fn ($value) => $value->option->position)
            ->mapWithKeys(fn ($value): array => [$value->option->name => $value->value])
            ->all();
    }
}
