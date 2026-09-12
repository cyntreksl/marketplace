<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class CancellationRefundCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $customerOrderNumber,
        public readonly string $sellerOrderNumber,
        public readonly string $amount,
        public readonly string $reference,
        public readonly ?string $recipientName = null,
        public readonly ?string $actionUrl = null,
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
    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->recipientName ?? ($notifiable instanceof User ? $notifiable->name : 'Customer');

        return (new MailMessage)
            ->subject("Refund recorded: {$this->sellerOrderNumber}")
            ->greeting("Hello {$name},")
            ->line('Your manual refund for the cancelled package has been recorded.')
            ->line('Amount: LKR '.Number::format((float) $this->amount, precision: 2, locale: 'en'))
            ->line("Reference: {$this->reference}")
            ->action('View order', $this->actionUrl ?? route('buyer.orders.show', ['customerOrder' => $this->customerOrderNumber]));
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
            'amount' => $this->amount,
            'reference' => $this->reference,
        ];
    }
}
