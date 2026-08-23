<?php

namespace App\Couriers;

use App\Contracts\CourierAdapter;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Str;

class ManualCourierAdapter implements CourierAdapter
{
    public function createShipment(SellerOrder $sellerOrder, string $courierName, ?string $trackingNumber = null): Shipment
    {
        return Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'courier_name' => $courierName,
            'tracking_number' => $trackingNumber ?? Str::upper('MAN-'.Str::random(10)),
            'status' => 'courier_assigned',
            'status_history' => [['status' => 'courier_assigned', 'at' => now()->toIso8601String()]],
        ]);
    }

    public function updateStatus(Shipment $shipment, string $status, ?string $reason = null): Shipment
    {
        $history = $shipment->status_history ?? [];
        $history[] = ['status' => $status, 'reason' => $reason, 'at' => now()->toIso8601String()];

        $shipment->update(['status' => $status, 'exception_reason' => $reason, 'status_history' => $history]);

        return $shipment->refresh();
    }
}
