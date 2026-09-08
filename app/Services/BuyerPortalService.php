<?php

namespace App\Services;

use App\BuyerOrderStage;
use App\Contracts\Repositories\BuyerAddressRepository;
use App\Contracts\Repositories\BuyerPortalRepository;
use App\Models\BuyerAddress;
use App\Models\CustomerOrder;
use App\Models\ListingMedia;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Review;
use App\Models\SellerOrder;
use App\Models\User;

class BuyerPortalService
{
    public function __construct(
        private readonly BuyerPortalRepository $portal,
        private readonly BuyerAddressRepository $addresses,
        private readonly BuyerOrderStageService $orderStages,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $buyer): array
    {
        $counts = $this->portal->orderCounts($buyer);

        return [
            'order_counts' => $counts,
            'pending_feedback_count' => $this->portal->pendingFeedbackCount($buyer),
            'active_return_count' => $this->portal->activeReturnCount($buyer),
            'default_shipping_address' => $this->serializeAddress($this->addresses->defaultFor($buyer, 'shipping')),
            'default_billing_address' => $this->serializeAddress($this->addresses->defaultFor($buyer, 'billing')),
            'security' => [
                'two_factor_enabled' => $buyer->hasEnabledTwoFactorAuthentication(),
                'passkey_count' => $buyer->passkeys()->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function activity(User $buyer): array
    {
        return [
            'recent_orders' => $this->portal->recentOrders($buyer, 4)->map(fn (CustomerOrder $order): array => $this->serializeOrder($order))->all(),
            'recent_payments' => $this->portal->recentPaymentAttempts($buyer, 4)->map(fn (PaymentAttempt $attempt): array => $this->serializePaymentAttempt($attempt))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function orders(User $buyer, BuyerOrderStage $stage): array
    {
        $orders = $this->portal->orders($buyer, $stage);

        return [
            'orders' => $orders->through(fn (CustomerOrder $order): array => $this->serializeOrder($order)),
            'counts' => $this->portal->orderCounts($buyer),
            'stage' => $stage->value,
            'stages' => array_map(fn (BuyerOrderStage $case): array => ['value' => $case->value, 'label' => $case->label()], BuyerOrderStage::cases()),
        ];
    }

    /** @return array<string, mixed> */
    public function order(User $buyer, CustomerOrder $customerOrder): array
    {
        return $this->serializeOrder($this->portal->order($buyer, $customerOrder), true);
    }

    /** @return array<string, mixed> */
    public function payments(User $buyer, string $status): array
    {
        return [
            'attempts' => $this->portal->paymentAttempts($buyer, $status)
                ->through(fn (PaymentAttempt $attempt): array => $this->serializePaymentAttempt($attempt)),
            'status' => $status,
        ];
    }

    /** @return array<string, mixed> */
    public function feedback(User $buyer, string $view): array
    {
        $feedback = $view === 'submitted'
            ? $this->portal->submittedFeedback($buyer)
                ->through(fn (Review $review): array => $this->serializeSubmittedFeedback($review))
            : $this->portal->awaitingFeedback($buyer)
                ->through(fn (OrderItem $item): array => $this->serializeAwaitingFeedback($item));

        return [
            'feedback' => $feedback,
            'view' => $view,
            'pending_count' => $this->portal->pendingFeedbackCount($buyer),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOrder(CustomerOrder $order, bool $detailed = false): array
    {
        $sellerOrders = $order->sellerOrders->map(fn (SellerOrder $sellerOrder): array => [
            'number' => $sellerOrder->number,
            'status' => $sellerOrder->status,
            'store_name' => $sellerOrder->sellerProfile->store_name,
            'delivered_at' => $sellerOrder->delivered_at?->toIso8601String(),
            'shipment' => $sellerOrder->shipment === null ? null : [
                'status' => $sellerOrder->shipment->status,
                'courier_name' => $sellerOrder->shipment->courier_name,
                'tracking_number' => $sellerOrder->shipment->tracking_number,
                'status_history' => $detailed ? $sellerOrder->shipment->status_history : null,
            ],
            'items' => $sellerOrder->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'variant_sku' => $item->variant_sku,
                'variant_options' => $item->variant_options,
                'pricing_tier' => $item->pricing_tier,
                'listing_slug' => $item->listing?->slug,
                'image_url' => $this->imageUrl($item),
                'review' => $item->review === null ? null : [
                    'rating' => $item->review->rating,
                    'comment' => $item->review->comment,
                ],
                'can_review' => $sellerOrder->delivered_at !== null && $item->review === null,
                'can_return' => $detailed && $this->canReturn($item, $sellerOrder),
            ])->all(),
        ])->all();

        return [
            'number' => $order->number,
            'stage' => $this->orderStages->classify($order)->value,
            'stage_label' => $this->orderStages->classify($order)->label(),
            'status' => $order->status,
            'subtotal' => $order->subtotal,
            'shipping_total' => $order->shipping_total,
            'total' => $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
            'shipping_address' => $detailed ? $order->shipping_address : null,
            'billing_address' => $detailed ? $order->billing_address : null,
            'seller_orders' => $sellerOrders,
            'payments' => $order->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'method' => $payment->method,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'attempts' => $payment->attempts->map(fn (PaymentAttempt $attempt): array => $this->serializePaymentAttempt($attempt, $payment->method, $payment->amount, $order->number, $order->status))->all(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializePaymentAttempt(
        PaymentAttempt $attempt,
        ?string $method = null,
        ?string $amount = null,
        ?string $orderNumber = null,
        ?string $orderStatus = null,
    ): array {
        $payment = $method === null ? $attempt->payment : null;

        return [
            'id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'status_label' => $attempt->status->label(),
            'attempted_at' => $attempt->attempted_at->toIso8601String(),
            'resolved_at' => $attempt->resolved_at?->toIso8601String(),
            'failure_summary' => $attempt->failure_summary,
            'method' => $method ?? $payment?->method,
            'amount' => $amount ?? $payment?->amount,
            'order_number' => $orderNumber ?? $payment?->customerOrder->number,
            'order_status' => $orderStatus ?? $payment?->customerOrder->status,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeAwaitingFeedback(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'image_url' => $this->imageUrl($item),
            'store_name' => $item->sellerOrder->sellerProfile->store_name,
            'order_number' => $item->sellerOrder->number,
            'delivered_at' => $item->sellerOrder->delivered_at?->toIso8601String(),
            'variant_options' => $item->variant_options,
            'pricing_tier' => $item->pricing_tier,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSubmittedFeedback(Review $review): array
    {
        return [
            'id' => $review->id,
            'title' => $review->orderItem->title,
            'image_url' => $this->imageUrl($review->orderItem),
            'store_name' => $review->orderItem->sellerOrder->sellerProfile->store_name,
            'order_number' => $review->orderItem->sellerOrder->number,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
            'variant_options' => $review->orderItem->variant_options,
        ];
    }

    private function imageUrl(OrderItem $item): ?string
    {
        /** @var ListingMedia|null $media */
        $media = $item->listing?->media->first();

        return $media?->urlForVariant('card');
    }

    private function canReturn(OrderItem $item, SellerOrder $sellerOrder): bool
    {
        $claimed = (int) $item->returnRequests->sum('quantity');
        $expiresAt = $sellerOrder->delivered_at?->addDays(7);

        return $sellerOrder->status === 'completed'
            && $expiresAt !== null
            && now()->lessThanOrEqualTo($expiresAt)
            && $claimed < $item->quantity;
    }

    /** @return array<string, mixed>|null */
    private function serializeAddress(?BuyerAddress $address): ?array
    {
        return $address?->only(['id', 'label', 'recipient_name', 'address_line_one', 'address_line_two', 'city', 'postal_code', 'phone']);
    }
}
