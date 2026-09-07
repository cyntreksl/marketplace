<?php

namespace App\Repositories;

use App\Contracts\Repositories\CustomerOrderRepository;
use App\Models\CustomerOrder;

class EloquentCustomerOrderRepository implements CustomerOrderRepository
{
    public function findForMetaConversion(int $id): ?CustomerOrder
    {
        return CustomerOrder::query()->find($id);
    }

    public function withConfirmationDetails(CustomerOrder $customerOrder): CustomerOrder
    {
        return $customerOrder->load([
            'sellerOrders:id,customer_order_id,seller_profile_id',
            'sellerOrders.sellerProfile:id,store_name',
            'sellerOrders.items:id,seller_order_id,title,variant_sku,variant_options,quantity,unit_price,total',
            'payments:id,customer_order_id,method,status,amount',
        ]);
    }

    public function withMetaConversionDetails(CustomerOrder $customerOrder): CustomerOrder
    {
        return $customerOrder->load([
            'buyer:id,name,email',
            'sellerOrders:id,customer_order_id',
            'sellerOrders.items:id,seller_order_id,listing_id,listing_variant_id,title,quantity,unit_price,total',
            'payments:id,customer_order_id,method,status,paid_at',
        ]);
    }

    public function clearMetaAttribution(CustomerOrder $customerOrder): void
    {
        $customerOrder->forceFill(['meta_attribution' => null])->save();
    }
}
