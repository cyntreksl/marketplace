<?php

namespace App\Services;

use App\BuyerOrderStage;
use App\Models\CustomerOrder;
use App\Models\SellerOrder;

class BuyerOrderStageService
{
    public function classify(CustomerOrder $order): BuyerOrderStage
    {
        if (in_array($order->status, ['expired', 'cancelled'], true)) {
            return BuyerOrderStage::Archived;
        }

        if ($order->status === 'pending_payment') {
            return BuyerOrderStage::ToPay;
        }

        if ($order->sellerOrders->isNotEmpty()
            && $order->sellerOrders->every(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === 'completed')) {
            return BuyerOrderStage::Completed;
        }

        if ($order->sellerOrders->isNotEmpty()
            && $order->sellerOrders->every(fn (SellerOrder $sellerOrder): bool => in_array($sellerOrder->status, ['shipped', 'completed'], true))) {
            return BuyerOrderStage::Shipped;
        }

        return BuyerOrderStage::Processing;
    }
}
