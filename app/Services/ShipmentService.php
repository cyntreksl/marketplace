<?php

namespace App\Services;

use App\Contracts\CourierAdapter;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(
        private readonly CourierAdapter $couriers,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function markReadyToShip(User $seller, SellerOrder $sellerOrder, ?string $courierName, ?string $trackingNumber): SellerOrder
    {
        return DB::transaction(function () use ($seller, $sellerOrder, $courierName, $trackingNumber): SellerOrder {
            $sellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);

            if ($sellerOrder->status !== 'paid') {
                throw ValidationException::withMessages(['order' => 'Only paid orders can be marked ready to ship.']);
            }

            $before = $sellerOrder->getAttributes();
            $sellerOrder->forceFill(['status' => 'ready_to_ship', 'ready_to_ship_at' => now()])->save();
            $shipment = $this->couriers->createShipment($sellerOrder, $courierName ?: 'Manual courier', $trackingNumber);
            $this->auditLogs->record($seller, 'seller_order.ready_to_ship', $sellerOrder, $before, $sellerOrder->getAttributes(), "Shipment {$shipment->tracking_number} assigned.");

            return $sellerOrder->load('shipment');
        });
    }
}
