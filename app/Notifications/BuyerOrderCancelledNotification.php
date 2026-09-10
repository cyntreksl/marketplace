<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BuyerOrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $customerOrderNumber,
        public readonly string $sellerOrderNumber,
        public readonly string $sellerName,
        public readonly string $reason,
        public readonly bool $refundPending,
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
        return (new MailMessage)
            ->subject("Order cancelled: {$this->sellerOrderNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The package {$this->sellerOrderNumber} from {$this->sellerName} has been cancelled.")
            ->line("Reason: {$this->reason}")
            ->when(
                $this->refundPending,
                fn (MailMessage $message): MailMessage => $message->line('A manual refund is pending. You will receive another update when it is recorded.'),
                fn (MailMessage $message): MailMessage => $message->line('No payment refund is required for this cancellation.'),
            )
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
            'seller_name' => $this->sellerName,
            'reason' => $this->reason,
            'refund_pending' => $this->refundPending,
        ];
    }
}
