<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $orderNumber,
        public readonly string $amount,
        public readonly ?string $recipientName = null,
        public readonly ?string $actionUrl = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->recipientName ?? ($notifiable instanceof User ? $notifiable->name : 'Customer');

        return (new MailMessage)->subject('Payment confirmed: '.$this->orderNumber)
            ->greeting("Hello {$name},")
            ->line('We received your payment of LKR '.$this->amount.'. Your order is confirmed.')
            ->action('View order', $this->actionUrl ?? route('checkout.thank_you.show', $this->orderNumber));
    }
}
