<?php

namespace App\Services;

use App\Contracts\Repositories\CheckoutRepository;
use App\Models\CustomerOrder;
use App\Notifications\SellerOrderReadyNotification;

class SellerOrderNotificationService
{
    public function __construct(private readonly CheckoutRepository $orders) {}

    public function notifyReady(CustomerOrder $customerOrder, string $paymentMethod): void
    {
        foreach ($this->orders->sellerOrdersForNotification($customerOrder) as $sellerOrder) {
            $sellerOrder->sellerProfile->user->notify(new SellerOrderReadyNotification(
                sellerOrderNumber: $sellerOrder->number,
                customerOrderNumber: $customerOrder->number,
                itemCount: (int) $sellerOrder->items->sum('quantity'),
                sellerSubtotal: $sellerOrder->subtotal,
                paymentMethod: $paymentMethod,
            ));
        }
    }
}
