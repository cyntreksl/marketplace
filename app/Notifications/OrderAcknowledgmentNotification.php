<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class OrderAcknowledgmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly float|string $orderTotal,
        public readonly string $paymentMethod,
        public readonly int $itemCount,
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
            ->subject("Order received: {$this->orderNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Thank you for your order. We've received {$this->itemCount} ".str('item')->plural($this->itemCount)." under order {$this->orderNumber}.")
            ->line('Order total: LKR '.Number::format((float) $this->orderTotal, precision: 2, locale: 'en'))
            ->line('Payment method: '.$this->paymentMethodLabel())
            ->line($this->paymentStatusMessage())
            ->action('View your order', route('buyer.orders.index'))
            ->line('We will email you again when there is an update to your order.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_number' => $this->orderNumber,
            'order_total' => $this->orderTotal,
            'payment_method' => $this->paymentMethod,
            'item_count' => $this->itemCount,
        ];
    }

    private function paymentMethodLabel(): string
    {
        return match ($this->paymentMethod) {
            'stripe' => 'Credit or debit card',
            'bank_transfer' => 'Bank transfer',
            'cod' => 'Cash on delivery',
            default => 'Selected payment method',
        };
    }

    private function paymentStatusMessage(): string
    {
        return match ($this->paymentMethod) {
            'cod' => 'Your order is confirmed. Please have the payment ready when your order arrives.',
            'bank_transfer' => 'Your order is awaiting bank transfer confirmation before it is released to the seller.',
            default => 'Your order is awaiting payment before it is released to the seller.',
        };
    }
}
