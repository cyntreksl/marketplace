<?php

namespace App\Services;

use App\Contracts\Repositories\CheckoutRepository;
use App\Contracts\Repositories\CustomerOrderRepository;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\SellerOrder;
use App\Notifications\OrderAcknowledgmentNotification;
use App\Support\CheckoutContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CashOnDeliveryService $cashOnDelivery,
        private readonly AuditLogService $auditLogs,
        private readonly CustomerOrderRepository $customerOrders,
        private readonly CheckoutRepository $repository,
        private readonly CartService $cartService,
        private readonly SellerOrderNotificationService $sellerOrderNotifications,
        private readonly PaymentAttemptService $paymentAttempts,
        private readonly MetaConversionsService $metaConversions,
        private readonly ListingPricingService $pricing,
        private readonly OrderCustomerNotificationService $customerNotifications,
        private readonly GuestOrderAccessService $guestOrderAccess,
    ) {}

    /**
     * @param  array<string, string|null>  $shippingAddress
     * @param  array<string, string|null>|null  $billingAddress
     * @param  array<string, string|null>|null  $metaAttribution
     */
    public function checkout(CheckoutContext $context, string $paymentMethod, array $shippingAddress, ?string $token = null, ?string $reviewHash = null, ?array $billingAddress = null, ?array $metaAttribution = null): CustomerOrder
    {
        $created = false;
        $order = DB::transaction(function () use ($context, $paymentMethod, $shippingAddress, $billingAddress, $metaAttribution, $token, $reviewHash, &$created): CustomerOrder {
            $cart = $context->buyer === null ? null : $this->repository->cart($context->buyer);
            if ($token !== null && ($existing = $this->repository->findSubmission($context->buyer, $token, $context->guestIdentityHash)) !== null) {
                return $this->repository->details($existing);
            }

            $cartItems = $context->buyer === null
                ? collect($context->guestCartEntries)->values()
                : $cart->items->map(fn ($item): array => $item->toArray())->values();
            $summary = $this->cartService->summarize($cartItems->all());
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
                $listing = $this->repository->listing((int) $cartItem['listing_id']);

                if ($listing->listing_type !== 'buy_now' || $listing->status !== 'approved' || ! $listing->is_active) {
                    throw ValidationException::withMessages(['cart' => "{$listing->title} is no longer available to purchase."]);
                }

                $variant = ($cartItem['listing_variant_id'] ?? null) === null
                    ? null
                    : $this->repository->variant((int) $cartItem['listing_variant_id']);

                if ($listing->product_type === 'variant' && ($variant === null || $variant->listing_id !== $listing->id || ! $variant->is_active)) {
                    throw ValidationException::withMessages(['cart' => "Choose an available option for {$listing->title}."]);
                }

                $availableQuantity = $variant?->availableQuantity() ?? ($listing->stock_quantity - $listing->reserved_quantity);
                if (! $listing->allow_backorders && $availableQuantity < (int) $cartItem['quantity']) {
                    throw ValidationException::withMessages(['cart' => "{$listing->title} no longer has enough stock."]);
                }

                $lockedListings[$listing->id] = $listing;
                if ($variant !== null) {
                    $lockedVariants[$this->entryKey($cartItem)] = $variant;
                }
            }

            $subtotal = BigDecimal::zero();
            foreach ($cartItems as $cartItem) {
                $listing = $lockedListings[$cartItem['listing_id']];
                $variant = $lockedVariants[$this->entryKey($cartItem)] ?? null;
                $quantity = (int) $cartItem['quantity'];
                $subtotal = $subtotal->plus(BigDecimal::of($this->buyNowPrice($listing, $variant, $quantity))->multipliedBy($quantity));
            }

            $shippingTotal = BigDecimal::of($summary['shippingTotal']);
            $total = $subtotal->plus($shippingTotal);
            $this->cashOnDelivery->ensureAllowed($paymentMethod, (string) $total);
            $lockedSummary = $summary;
            $lockedSummary['items'] = array_map(function (array $item) use ($lockedListings, $lockedVariants): array {
                $lockedPricing = $this->priceForQuantity($lockedListings[$item['listing_id']], $lockedVariants[$this->entryKey($item)] ?? null, $item['quantity']);
                $item['unitPrice'] = $lockedPricing['unitPrice'];
                $item['pricingTier'] = $lockedPricing['tier'];
                $item['minimumQuantity'] = $lockedPricing['minimumQuantity'];
                $item['appliedTierMinimumQuantity'] = $lockedPricing['appliedTierMinimumQuantity'];

                return $item;
            }, $summary['items']);
            $lockedSummary['subtotal'] = (string) $subtotal->toScale(2);
            $lockedSummary['total'] = (string) $total->toScale(2);
            if ($reviewHash !== null && ! hash_equals($reviewHash, $this->reviewHash($lockedSummary))) {
                throw ValidationException::withMessages(['cart' => 'Prices changed. Reload the review page before placing your order.']);
            }
            $order = $this->repository->createOrder([
                'checkout_token' => $token,
                'checkout_identity_hash' => $context->guestIdentityHash,
                'guest_access_token_hash' => $context->guestAccessTokenHash(),
                'marketing_opt_in' => $context->marketingOptIn,
                'number' => (string) Str::uuid(),
                'buyer_id' => $context->buyer?->id,
                'contact_email' => Str::lower($context->contactEmail),
                'status' => $paymentMethod === 'cod' ? 'confirmed' : 'pending_payment',
                'subtotal' => (string) $subtotal,
                'total' => (string) $total,
                'shipping_total' => (string) $shippingTotal,
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress ?? $shippingAddress,
                'meta_attribution' => $metaAttribution,
            ]);

            foreach ($cartItems->groupBy(fn (array $item): int => $lockedListings[$item['listing_id']]->seller_profile_id) as $sellerProfileId => $items) {
                $sellerSubtotal = BigDecimal::zero();
                foreach ($items as $item) {
                    $listing = $lockedListings[$item['listing_id']];
                    $variant = $lockedVariants[$this->entryKey($item)] ?? null;
                    $quantity = (int) $item['quantity'];
                    $sellerSubtotal = $sellerSubtotal->plus(BigDecimal::of($this->buyNowPrice($listing, $variant, $quantity))->multipliedBy($quantity));
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
                    $listing = $lockedListings[$item['listing_id']];
                    $variant = $lockedVariants[$this->entryKey($item)] ?? null;
                    $quantity = (int) $item['quantity'];
                    $lockedPricing = $this->priceForQuantity($listing, $variant, $quantity);
                    $effectivePrice = $lockedPricing['unitPrice'];
                    $lineTotal = BigDecimal::of($effectivePrice)->multipliedBy($quantity);
                    $commission = $lineTotal->multipliedBy((string) $listing->commission_percentage)->dividedBy(100, 2, RoundingMode::Down);
                    $this->repository->addItem($sellerOrder, [
                        'listing_id' => $listing->id,
                        'listing_variant_id' => $variant?->id,
                        'title' => $listing->title,
                        'variant_sku' => $variant?->sku,
                        'variant_options' => $variant === null ? null : $this->variantOptions($variant),
                        'quantity' => $quantity,
                        'unit_price' => $effectivePrice,
                        'pricing_tier' => $lockedPricing['tier'],
                        'commission_percentage' => $listing->commission_percentage,
                        'commission_amount' => (string) $commission,
                        'total' => (string) $lineTotal,
                    ]);
                    $this->repository->reserve($listing, $variant, $quantity);
                }
            }

            $payment = $this->repository->createPayment([
                'customer_order_id' => $order->id,
                'method' => $paymentMethod,
                'status' => $paymentMethod === 'cod' ? 'pending_collection' : 'pending',
                'idempotency_key' => (string) Str::uuid(),
                'amount' => (string) $total,
                'expires_at' => $paymentMethod === 'stripe' ? now()->addMinutes(30) : null,
            ]);
            $this->paymentAttempts->begin($payment);
            if ($cart !== null) {
                $this->repository->clear($cart);
            }
            $created = true;
            $this->auditLogs->record($context->buyer, 'checkout.created', $order, after: $order->getAttributes());

            return $this->repository->details($order);
        }, attempts: 3);

        if ($created) {
            $this->customerNotifications->notify($order, new OrderAcknowledgmentNotification(
                orderNumber: $order->number,
                orderTotal: $order->total,
                paymentMethod: $paymentMethod,
                itemCount: (int) $order->sellerOrders->sum(fn (SellerOrder $sellerOrder): int => (int) $sellerOrder->items->sum('quantity')),
                recipientName: $this->customerNotifications->recipientName($order),
                confirmationUrl: $this->guestOrderAccess->confirmationUrl($order, $context->guestAccessToken),
                claimUrl: $context->isGuest() ? $this->guestOrderAccess->claimUrl($order) : null,
            ));

            if ($paymentMethod === 'cod') {
                $this->sellerOrderNotifications->notifyReady($order, $paymentMethod);
                $this->metaConversions->trackPurchase($order);
            }
        }

        return $order;
    }

    /** @param array<string, mixed> $entry */
    private function entryKey(array $entry): string
    {
        return (string) $entry['id'];
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
                    'listingId' => $item->listing_id,
                    'listingVariantId' => $item->listing_variant_id,
                    'title' => $item->title,
                    'seller' => $sellerOrder->sellerProfile->store_name ?? 'Marketplace seller',
                    'variantSku' => $item->variant_sku,
                    'variantOptions' => $item->variant_options,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    'pricingTier' => $item->pricing_tier,
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
            'billingAddress' => $customerOrder->billing_address ?? $shippingAddress,
            'billingSameAsShipping' => $customerOrder->billing_address === null || $customerOrder->billing_address === $shippingAddress,
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
        $items = array_map(fn (array $item): array => [
            $item['listing_id'],
            $item['listing_variant_id'],
            $item['quantity'],
            $item['unitPrice'],
            $item['pricingTier'] ?? null,
            $item['appliedTierMinimumQuantity'] ?? null,
        ], $summary['items']);
        sort($items);

        return hash('sha256', json_encode([$items, $summary['subtotal'], $summary['shippingTotal'], $summary['total']], JSON_THROW_ON_ERROR));
    }

    private function orderNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }

    private function buyNowPrice(Listing $listing, ?ListingVariant $variant, int $quantity): string
    {
        return $this->priceForQuantity($listing, $variant, $quantity)['unitPrice'];
    }

    /** @return array{unitPrice: string, tier: 'retail'|'wholesale', minimumQuantity: int, appliedTierMinimumQuantity: int|null} */
    private function priceForQuantity(Listing $listing, ?ListingVariant $variant, int $quantity): array
    {
        $price = $this->pricing->forQuantity($listing, $variant, $quantity);

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
