<?php

namespace App\Repositories;

use App\Contracts\Repositories\OrderTrackingRepository;
use App\Models\CustomerOrder;

class EloquentOrderTrackingRepository implements OrderTrackingRepository
{
    public function findForPublicLookup(string $number, string $email): ?CustomerOrder
    {
        return CustomerOrder::query()
            ->where('number', $number)
            ->whereHas('buyer', fn ($query) => $query->where('email', $email))
            ->with([
                'sellerOrders:id,customer_order_id,seller_profile_id,number,status,cancellation_reason,processing_at,ready_to_ship_at,shipped_at,delivered_at,cancelled_at',
                'sellerOrders.sellerProfile:id,store_name',
                'sellerOrders.shipment:id,seller_order_id,courier_name,tracking_number,status,status_history',
            ])
            ->first();
    }
}
