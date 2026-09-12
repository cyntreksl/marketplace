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
     * @param  array{search?: string, status?: string, payment_method?: string, payment_status?: string, created_from?: string, created_to?: string, sort?: string}  $filters
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
                'payment_method' => $filters['payment_method'] ?? 'all',
                'payment_status' => $filters['payment_status'] ?? 'all',
                'created_from' => $filters['created_from'] ?? '',
                'created_to' => $filters['created_to'] ?? '',
                'sort' => $filters['sort'] ?? 'newest',
            ],
            'statuses' => [
                ['value' => 'all', 'label' => 'All statuses'],
                ['value' => 'pending_payment', 'label' => 'Pending payment'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
                ['value' => 'expired', 'label' => 'Expired'],
            ],
            'paymentMethods' => [
                ['value' => 'all', 'label' => 'All payment methods'],
                ['value' => 'stripe', 'label' => 'Stripe'],
                ['value' => 'cod', 'label' => 'Cash on delivery'],
                ['value' => 'bank_transfer', 'label' => 'Bank transfer'],
            ],
            'paymentStatuses' => [
                ['value' => 'all', 'label' => 'All payment statuses'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'pending_collection', 'label' => 'Pending collection'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'expired', 'label' => 'Expired'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
                ['value' => 'partially_refunded', 'label' => 'Partially refunded'],
                ['value' => 'refunded', 'label' => 'Refunded'],
            ],
            'exportColumns' => AdminOrderExportService::columnOptions(),
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
                'name' => $customerOrder->buyer->name ?? $customerOrder->shipping_address['recipient_name'] ?? 'Guest customer',
                'email' => $customerOrder->contact_email,
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
                'name' => $order->buyer->name ?? $order->shipping_address['recipient_name'] ?? 'Guest customer',
                'email' => $order->contact_email,
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
