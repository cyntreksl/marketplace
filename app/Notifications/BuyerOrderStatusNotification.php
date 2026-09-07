<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuyerOrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $customerOrderNumber,
        public readonly string $sellerOrderNumber,
        public readonly string $status,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $delivered = $this->status === 'delivered';

        return (new MailMessage)
            ->subject(($delivered ? 'Order delivered: ' : 'Order shipped: ').$this->sellerOrderNumber)
            ->greeting("Hello {$notifiable->name},")
            ->line($delivered
                ? "Seller order {$this->sellerOrderNumber} was marked delivered. Your return window is now open."
                : "Seller order {$this->sellerOrderNumber} is on its way.")
            ->action('View order', route('buyer.orders.show', ['customerOrder' => $this->customerOrderNumber]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'customer_order_number' => $this->customerOrderNumber,
            'seller_order_number' => $this->sellerOrderNumber,
            'status' => $this->status,
        ];
    }
}
