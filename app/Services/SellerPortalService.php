<?php

namespace App\Services;

use App\Contracts\Repositories\SellerPortalRepository;
use App\Models\OrderItem;
use App\Models\ProductQuestion;
use App\Models\SellerOrder;
use App\Models\User;
use App\SellerOrderStatus;

class SellerPortalService
{
    public function __construct(private readonly SellerPortalRepository $portal) {}

    /** @return array<string, mixed> */
    public function dashboard(User $seller): array
    {
        return ['metrics' => $this->portal->metrics($seller)];
    }

    /** @return array<string, mixed> */
    public function activity(User $seller): array
    {
        return [
            'recent_orders' => $this->portal->recentOrders($seller, 5)->map(fn (SellerOrder $order): array => $this->serializeOrder($order))->all(),
            'low_stock_products' => $this->portal->lowStockProducts($seller, 5)->map(fn ($listing): array => [
                'id' => $listing->id,
                'title' => $listing->title,
                'available_quantity' => max(0, $listing->stock_quantity - $listing->reserved_quantity),
            ])->all(),
            'unanswered_questions' => $this->portal->unansweredQuestions($seller, 5)->map(fn (ProductQuestion $question): array => [
                'id' => $question->id,
                'question' => $question->question,
                'listing_title' => $question->listing->title,
                'asker_name' => $question->asker->name,
            ])->all(),
        ];
    }

    /**
     * @param  array{q?: string, status?: string, sort?: string}  $filters
     * @return array<string, mixed>
     */
    public function orders(User $seller, array $filters): array
    {
        return [
            'orders' => $this->portal->orders($seller, $filters)->through(fn (SellerOrder $order): array => $this->serializeOrder($order)),
            'counts' => $this->portal->orderCounts($seller),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'sort' => $filters['sort'] ?? 'newest',
            ],
            'statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ...array_map(fn (SellerOrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()], [SellerOrderStatus::PendingPayment, SellerOrderStatus::Paid, SellerOrderStatus::Processing, SellerOrderStatus::ReadyToShip, SellerOrderStatus::Shipped, SellerOrderStatus::Completed]),
                ['value' => 'archived', 'label' => 'Archived'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function order(User $seller, SellerOrder $sellerOrder): array
    {
        return $this->serializeOrder($this->portal->order($seller, $sellerOrder), true);
    }

    /** @return array<string, mixed> */
    public function wallet(User $seller): array
    {
        $metrics = $this->portal->metrics($seller);

        return [
            'availableBalance' => $metrics['available_balance'],
            'entries' => $this->portal->transactions($seller),
            'payouts' => $this->portal->payouts($seller),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOrder(SellerOrder $order, bool $detailed = false): array
    {
        $status = SellerOrderStatus::tryFrom($order->status);
        $address = $order->customerOrder->shipping_address;

        return [
            'id' => $order->id,
            'number' => $order->number,
            'customer_order_number' => $order->customerOrder->number,
            'status' => $order->status,
            'status_label' => $status?->label() ?? str($order->status)->headline()->toString(),
            'created_at' => $order->created_at?->toIso8601String(),
            'recipient_name' => $address['name'] ?? $address['recipient_name'] ?? $order->customerOrder->buyer->name,
            'subtotal' => $order->subtotal,
            'shipping_charge' => $order->shipping_charge,
            'seller_earnings' => $order->seller_earnings,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'variant_options' => $item->variant_options,
            ])->all(),
            'shipment' => $order->shipment === null ? null : [
                'courier_name' => $order->shipment->courier_name,
                'tracking_number' => $order->shipment->tracking_number,
                'status' => $order->shipment->status,
                'status_history' => $detailed ? ($order->shipment->status_history ?? []) : null,
            ],
            'next_action' => $this->nextAction($order->status),
            'recipient' => $detailed ? [
                'name' => $address['name'] ?? $address['recipient_name'] ?? $order->customerOrder->buyer->name,
                'phone' => $address['phone'] ?? null,
                'address_line_one' => $address['address_line_one'] ?? $address['line_1'] ?? $address['line1'] ?? null,
                'address_line_two' => $address['address_line_two'] ?? $address['line_2'] ?? $address['line2'] ?? null,
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ] : null,
            'payment_method' => $detailed ? $order->customerOrder->payments->first()?->method : null,
            'timeline' => $detailed ? $this->timeline($order) : null,
        ];
    }

    /** @return array<int, array{status: string, label: string, at: string|null, complete: bool, current: bool}> */
    private function timeline(SellerOrder $order): array
    {
        $current = SellerOrderStatus::tryFrom($order->status);
        $rank = [
            SellerOrderStatus::PendingPayment->value => 0,
            SellerOrderStatus::Paid->value => 1,
            SellerOrderStatus::Processing->value => 2,
            SellerOrderStatus::ReadyToShip->value => 3,
            SellerOrderStatus::Shipped->value => 4,
            SellerOrderStatus::Completed->value => 5,
        ];
        $currentRank = $rank[$order->status] ?? -1;
        $steps = [
            [SellerOrderStatus::Paid, $order->created_at],
            [SellerOrderStatus::Processing, $order->processing_at],
            [SellerOrderStatus::ReadyToShip, $order->ready_to_ship_at],
            [SellerOrderStatus::Shipped, $order->shipped_at],
            [SellerOrderStatus::Completed, $order->delivered_at ?? $order->completed_at],
        ];

        return array_map(fn (array $step): array => [
            'status' => $step[0]->value,
            'label' => $step[0] === SellerOrderStatus::Paid ? 'Order received' : $step[0]->label(),
            'at' => $step[1]?->toIso8601String(),
            'complete' => $currentRank >= $rank[$step[0]->value],
            'current' => $current === $step[0],
        ], $steps);
    }

    /** @return array{action: string, label: string}|null */
    private function nextAction(string $status): ?array
    {
        return match ($status) {
            SellerOrderStatus::Paid->value => ['action' => 'processing', 'label' => 'Start processing'],
            SellerOrderStatus::Processing->value => ['action' => 'ready', 'label' => 'Mark ready to ship'],
            SellerOrderStatus::ReadyToShip->value => ['action' => 'shipped', 'label' => 'Dispatch order'],
            SellerOrderStatus::Shipped->value => ['action' => 'delivered', 'label' => 'Confirm delivery'],
            default => null,
        };
    }
}
