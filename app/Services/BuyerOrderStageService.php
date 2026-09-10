<?php

namespace App\Services;

use App\BuyerOrderStage;
use App\Models\CustomerOrder;
use App\Models\SellerOrder;
use Illuminate\Support\Collection;

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

        $activePackages = $this->activePackages($order);

        if ($activePackages->isEmpty()) {
            return BuyerOrderStage::Archived;
        }

        if ($activePackages->every(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === 'completed')) {
            return BuyerOrderStage::Completed;
        }

        if ($activePackages->every(fn (SellerOrder $sellerOrder): bool => in_array($sellerOrder->status, ['shipped', 'completed'], true))) {
            return BuyerOrderStage::Shipped;
        }

        return BuyerOrderStage::Processing;
    }

    /** @return Collection<int, SellerOrder> */
    private function activePackages(CustomerOrder $order): Collection
    {
        return $order->sellerOrders->reject(
            fn (SellerOrder $sellerOrder): bool => in_array($sellerOrder->status, ['cancelled', 'expired'], true),
        );
    }
}
