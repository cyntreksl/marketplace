<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class SellerOrderReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $sellerOrderNumber,
        public readonly string $customerOrderNumber,
        public readonly int $itemCount,
        public readonly float|string $sellerSubtotal,
        public readonly string $paymentMethod,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New order ready: {$this->sellerOrderNumber}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new order is ready for fulfilment under seller order {$this->sellerOrderNumber}.")
            ->line("Customer order: {$this->customerOrderNumber}")
            ->line($this->itemCount.' '.str('item')->plural($this->itemCount).' · LKR '.Number::format((float) $this->sellerSubtotal, precision: 2, locale: 'en'))
            ->line($this->paymentMessage())
            ->action('Open seller orders', route('seller.orders.index'))
            ->line('Review the order and mark it ready to ship when it has been prepared.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'seller_order_number' => $this->sellerOrderNumber,
            'customer_order_number' => $this->customerOrderNumber,
            'item_count' => $this->itemCount,
            'seller_subtotal' => $this->sellerSubtotal,
            'payment_method' => $this->paymentMethod,
        ];
    }

    private function paymentMessage(): string
    {
        return match ($this->paymentMethod) {
            'cod' => 'Payment method: Cash on delivery. Collect payment when the order is delivered.',
            'stripe' => 'Payment status: Card payment confirmed.',
            default => 'Payment status: Confirmed.',
        };
    }
}
