<?php

namespace App\Services;

use App\Models\CustomerOrder;
use Illuminate\Notifications\Notification as NotificationMessage;
use Illuminate\Support\Facades\Notification;

class OrderCustomerNotificationService
{
    public function notify(CustomerOrder $order, NotificationMessage $notification): void
    {
        if ($order->buyer !== null) {
            $order->buyer->notify($notification);

            return;
        }

        Notification::route('mail', [
            $order->contact_email => $this->recipientName($order),
        ])->notify($notification);
    }

    public function recipientName(CustomerOrder $order): string
    {
        return (string) ($order->shipping_address['recipient_name'] ?? $order->buyer->name ?? 'Customer');
    }
}
