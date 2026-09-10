<?php

namespace App\Services;

use App\Contracts\Repositories\OrderOperationsRepository;
use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\SellerOrder;

class AdminOrderService
{
    public function __construct(
        private readonly OrderOperationsRepository $orders,
        private readonly SellerPortalService $sellerPortal,
    ) {}

    /**
     * @param  array{search?: string, status?: string, sort?: string}  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        return [
            'orders' => $this->orders->paginateForAdmin($filters)
                ->through(fn (CustomerOrder $order): array => $this->serializeSummary($order)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'sort' => $filters['sort'] ?? 'newest',
            ],
            'statuses' => [
                ['value' => 'all', 'label' => 'All statuses'],
                ['value' => 'pending_payment', 'label' => 'Pending payment'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
                ['value' => 'expired', 'label' => 'Expired'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function show(CustomerOrder $customerOrder): array
    {
        $customerOrder = $this->orders->findDetailedForAdmin($customerOrder);

        return [
            'number' => $customerOrder->number,
            'status' => $customerOrder->status,
            'subtotal' => $customerOrder->subtotal,
            'shipping_total' => $customerOrder->shipping_total,
            'total' => $customerOrder->total,
            'created_at' => $customerOrder->created_at?->toIso8601String(),
            'buyer' => [
                'name' => $customerOrder->buyer->name,
                'email' => $customerOrder->buyer->email,
            ],
            'shipping_address' => $customerOrder->shipping_address,
            'billing_address' => $customerOrder->billing_address,
            'payments' => $customerOrder->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->method,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->all(),
            'packages' => $customerOrder->sellerOrders->map(function (SellerOrder $sellerOrder) use ($customerOrder): array {
                $sellerOrder->setRelation('customerOrder', $customerOrder);

                return [
                    ...$this->sellerPortal->serializeOrder($sellerOrder, true),
                    'seller' => [
                        'id' => $sellerOrder->sellerProfile->id,
                        'store_name' => $sellerOrder->sellerProfile->store_name,
                    ],
                ];
            })->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSummary(CustomerOrder $order): array
    {
        return [
            'number' => $order->number,
            'status' => $order->status,
            'total' => $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
            'buyer' => [
                'name' => $order->buyer->name,
                'email' => $order->buyer->email,
            ],
            'payments' => $order->payments->map(fn (Payment $payment): array => [
                'method' => $payment->method,
                'status' => $payment->status,
                'amount' => $payment->amount,
            ])->all(),
            'packages' => $order->sellerOrders->map(fn (SellerOrder $sellerOrder): array => [
                'number' => $sellerOrder->number,
                'status' => $sellerOrder->status,
                'store_name' => $sellerOrder->sellerProfile->store_name,
            ])->all(),
        ];
    }
}
