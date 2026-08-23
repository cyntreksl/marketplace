<?php

namespace App\Contracts;

use App\Models\SellerOrder;
use App\Models\Shipment;

interface CourierAdapter
{
    /**
     * Create a manual shipment record for a seller order.
     */
    public function createShipment(SellerOrder $sellerOrder, string $courierName, ?string $trackingNumber = null): Shipment;

    /**
     * Update the shipment status received from a courier or an operations user.
     */
    public function updateStatus(Shipment $shipment, string $status, ?string $reason = null): Shipment;
}
