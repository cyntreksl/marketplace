<?php

namespace App\Services;

use App\Contracts\CourierAdapter;
use App\Contracts\Repositories\SellerPortalRepository;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\BuyerOrderStatusNotification;
use App\SellerOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerOrderWorkflowService
{
    public function __construct(
        private readonly SellerPortalRepository $orders,
        private readonly CourierAdapter $courier,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function startProcessing(User $seller, int $sellerOrderId): SellerOrder
    {
        return $this->transition($seller, $sellerOrderId, SellerOrderStatus::Paid, SellerOrderStatus::Processing, 'processing_at');
    }

    public function markReady(User $seller, int $sellerOrderId): SellerOrder
    {
        return $this->transition($seller, $sellerOrderId, SellerOrderStatus::Processing, SellerOrderStatus::ReadyToShip, 'ready_to_ship_at');
    }

    public function ship(User $seller, int $sellerOrderId, string $courierName, ?string $trackingNumber): SellerOrder
    {
        $notify = false;
        $order = DB::transaction(function () use ($seller, $sellerOrderId, $courierName, $trackingNumber, &$notify): SellerOrder {
            $order = $this->ownedLockedOrder($seller, $sellerOrderId);
            if ($order->status === SellerOrderStatus::Shipped->value) {
                return $order;
            }
            $this->ensureCurrentStatus($order, SellerOrderStatus::ReadyToShip);
            $before = $order->getAttributes();
            $shipment = $order->shipment ?? $this->courier->createShipment($order, $courierName, $trackingNumber ?: null);
            $this->courier->updateStatus($shipment, SellerOrderStatus::Shipped->value);
            $order->forceFill(['status' => SellerOrderStatus::Shipped->value, 'shipped_at' => now()])->save();
            $this->auditLogs->record($seller, 'seller_order.shipped', $order, $before, $order->getAttributes());
            $notify = true;

            return $order->refresh()->load(['shipment', 'customerOrder.buyer']);
        });

        if ($notify && $order->customerOrder?->buyer !== null) {
            $order->customerOrder->buyer->notify(new BuyerOrderStatusNotification($order->customerOrder->number, $order->number, 'shipped'));
        }

        return $order;
    }

    public function deliver(User $seller, int $sellerOrderId): SellerOrder
    {
        $notify = false;
        $order = DB::transaction(function () use ($seller, $sellerOrderId, &$notify): SellerOrder {
            $order = $this->ownedLockedOrder($seller, $sellerOrderId);
            if ($order->status === SellerOrderStatus::Completed->value) {
                return $order;
            }
            $this->ensureCurrentStatus($order, SellerOrderStatus::Shipped);
            if ($order->shipment === null) {
                throw ValidationException::withMessages(['status' => 'A shipped order must have courier information before delivery can be confirmed.']);
            }
            $before = $order->getAttributes();
            $this->courier->updateStatus($order->shipment, 'delivered');
            $order->forceFill(['status' => SellerOrderStatus::Completed->value, 'delivered_at' => now(), 'completed_at' => now()])->save();
            $this->auditLogs->record($seller, 'seller_order.completed', $order, $before, $order->getAttributes());
            $notify = true;

            return $order->refresh()->load(['shipment', 'customerOrder.buyer']);
        });

        if ($notify && $order->customerOrder?->buyer !== null) {
            $order->customerOrder->buyer->notify(new BuyerOrderStatusNotification($order->customerOrder->number, $order->number, 'delivered'));
        }

        return $order;
    }

    private function transition(User $seller, int $sellerOrderId, SellerOrderStatus $from, SellerOrderStatus $to, string $timestamp): SellerOrder
    {
        return DB::transaction(function () use ($seller, $sellerOrderId, $from, $to, $timestamp): SellerOrder {
            $order = $this->ownedLockedOrder($seller, $sellerOrderId);
            if ($order->status === $to->value) {
                return $order;
            }
            $this->ensureCurrentStatus($order, $from);
            $before = $order->getAttributes();
            $order->forceFill(['status' => $to->value, $timestamp => now()])->save();
            $this->auditLogs->record($seller, 'seller_order.'.$to->value, $order, $before, $order->getAttributes());

            return $order->refresh();
        });
    }

    private function ownedLockedOrder(User $seller, int $sellerOrderId): SellerOrder
    {
        return $this->orders->lockOrder($seller, $sellerOrderId) ?? abort(404);
    }

    private function ensureCurrentStatus(SellerOrder $order, SellerOrderStatus $expected): void
    {
        if ($order->status !== $expected->value) {
            throw ValidationException::withMessages(['status' => "This order must be {$expected->label()} before continuing."]);
        }
    }
}
