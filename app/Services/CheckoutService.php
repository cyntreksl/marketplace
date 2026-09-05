<?php

namespace App\Services;

use App\Contracts\Repositories\CheckoutRepository;
use App\Contracts\Repositories\CustomerOrderRepository;
use App\Models\CartItem;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\OrderAcknowledgmentNotification;
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
        private readonly CustomerOrderRepository $customerOrders,
        private readonly CheckoutRepository $repository,
        private readonly CartService $cartService,
    ) {}

    /** @param array<string, string|null> $shippingAddress */
    public function checkout(User $buyer, string $paymentMethod, array $shippingAddress, ?string $token = null, ?string $reviewHash = null): CustomerOrder
    {
        $created = false;
        $order = DB::transaction(function () use ($buyer, $paymentMethod, $shippingAddress, $token, $reviewHash, &$created): CustomerOrder {
            $cart = $this->repository->cart($buyer);
            if ($token !== null && ($existing = $this->repository->findSubmission($buyer, $token)) !== null) {
                return $this->repository->details($existing);
            }
            $cartItems = $cart->items;
            $summary = $this->cartService->summarize($cartItems->toArray());
            if (! $summary['canCheckout']) {
                throw ValidationException::withMessages(['cart' => 'Update unavailable items in your cart before checking out.']);
            }
            if (! in_array($paymentMethod, $summary['paymentMethods'], true)) {
                throw ValidationException::withMessages(['payment_method' => 'This payment method is unavailable for your order.']);
            }
            if ($reviewHash !== null && ! hash_equals($reviewHash, $this->reviewHash($summary))) {
                throw ValidationException::withMessages(['cart' => 'Your cart or prices changed. Reload the review page to confirm the updated total.']);
            }

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $lockedListings = [];
            $lockedVariants = [];
            foreach ($cartItems as $cartItem) {
                $listing = $this->repository->listing($cartItem->listing_id);

                if ($listing->listing_type !== 'buy_now' || $listing->status !== 'approved' || ! $listing->is_active) {
                    throw ValidationException::withMessages(['cart' => "{$listing->title} is no longer available to purchase."]);
                }

                $variant = $cartItem->listing_variant_id === null
                    ? null
                    : $this->repository->variant($cartItem->listing_variant_id);

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

            $shippingTotal = BigDecimal::of($summary['shippingTotal']);
            $total = $subtotal->plus($shippingTotal);
            $lockedSummary = $summary;
            $lockedSummary['items'] = array_map(function (array $item) use ($lockedListings, $lockedVariants): array {
                $item['unitPrice'] = $this->buyNowPrice($lockedListings[$item['listing_id']], $lockedVariants[$item['id']] ?? null);

                return $item;
            }, $summary['items']);
            $lockedSummary['subtotal'] = (string) $subtotal->toScale(2);
            $lockedSummary['total'] = (string) $total->toScale(2);
            if ($reviewHash !== null && ! hash_equals($reviewHash, $this->reviewHash($lockedSummary))) {
                throw ValidationException::withMessages(['cart' => 'Prices changed. Reload the review page before placing your order.']);
            }
            $order = $this->repository->createOrder([
                'checkout_token' => $token,
                'number' => (string) Str::uuid(),
                'buyer_id' => $buyer->id,
                'status' => $paymentMethod === 'cod' ? 'confirmed' : 'pending_payment',
                'subtotal' => (string) $subtotal,
                'total' => (string) $total,
                'shipping_total' => (string) $shippingTotal,
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cartItems->groupBy(fn (CartItem $item): int => $item->listing->seller_profile_id) as $sellerProfileId => $items) {
                $sellerSubtotal = BigDecimal::zero();
                foreach ($items as $item) {
                    $listing = $lockedListings[$item->listing_id];
                    $variant = $lockedVariants[$item->id] ?? null;
                    $sellerSubtotal = $sellerSubtotal->plus(BigDecimal::of($this->buyNowPrice($listing, $variant))->multipliedBy($item->quantity));
                }

                $sellerOrder = $this->repository->createSellerOrder([
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
                    $this->repository->addItem($sellerOrder, [
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
                    $this->repository->reserve($listing, $variant, $item->quantity);
                }
            }

            $this->repository->createPayment([
                'customer_order_id' => $order->id,
                'method' => $paymentMethod,
                'status' => $paymentMethod === 'cod' ? 'pending_collection' : 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'amount' => (string) $total,
                'expires_at' => $paymentMethod === 'stripe' ? now()->addMinutes(30) : null,
            ]);
            $this->repository->clear($cart);
            $created = true;
            $this->auditLogs->record($buyer, 'checkout.created', $order, after: $order->getAttributes());

            return $this->repository->details($order);
        }, attempts: 3);

        if ($created) {
            $buyer->notify(new OrderAcknowledgmentNotification(
                orderNumber: $order->number,
                orderTotal: $order->total,
                paymentMethod: $paymentMethod,
                itemCount: (int) $order->sellerOrders->sum(fn (SellerOrder $sellerOrder): int => (int) $sellerOrder->items->sum('quantity')),
            ));
        }

        return $order;
    }

    /** @return array<string, mixed> */
    public function confirmationSummary(CustomerOrder $customerOrder): array
    {
        $customerOrder = $this->customerOrders->withConfirmationDetails($customerOrder);
        $shippingAddress = $customerOrder->shipping_address;
        $payment = $customerOrder->payments->first();
        $items = [];

        foreach ($customerOrder->sellerOrders as $sellerOrder) {
            foreach ($sellerOrder->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'seller' => $sellerOrder->sellerProfile->store_name ?? 'Marketplace seller',
                    'variantSku' => $item->variant_sku,
                    'variantOptions' => $item->variant_options,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    'total' => $item->total,
                ];
            }
        }

        return [
            'number' => $customerOrder->number,
            'status' => $customerOrder->status,
            'placedAt' => $customerOrder->created_at?->toIso8601String(),
            'subtotal' => $customerOrder->subtotal,
            'shippingTotal' => $customerOrder->shipping_total,
            'total' => $customerOrder->total,
            'shippingAddress' => $shippingAddress,
            'billingAddress' => $shippingAddress,
            'payment' => $payment === null ? null : [
                'method' => $payment->method,
                'status' => $payment->status,
                'amount' => $payment->amount,
            ],
            'items' => $items,
        ];
    }

    /** @param array<string, mixed> $summary */
    public function reviewHash(array $summary): string
    {
        $items = array_map(fn (array $item): array => [$item['listing_id'], $item['listing_variant_id'], $item['quantity'], $item['unitPrice']], $summary['items']);
        sort($items);

        return hash('sha256', json_encode([$items, $summary['subtotal'], $summary['shippingTotal'], $summary['total']], JSON_THROW_ON_ERROR));
    }

    private function orderNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
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
