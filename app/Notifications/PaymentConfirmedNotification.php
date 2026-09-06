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

    public function __construct(public readonly string $orderNumber, public readonly string $amount)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Payment confirmed: '.$this->orderNumber)
            ->greeting("Hello {$notifiable->name},")
            ->line('We received your payment of LKR '.$this->amount.'. Your order is confirmed.')
            ->action('View order', route('checkout.thank_you.show', $this->orderNumber));
    }
}
