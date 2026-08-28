<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReturnRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly int $returnRequestId,
        public readonly string $itemTitle,
        public readonly int $quantity,
        public readonly string $buyerName,
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
            ->subject('New return request: '.$this->itemTitle)
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->buyerName} requested to return {$this->quantity} × {$this->itemTitle}.")
            ->line('Please review the buyer description and any evidence before making your final decision.')
            ->action('Review return request', route('seller.returns.index'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'return_request_id' => $this->returnRequestId,
            'item_title' => $this->itemTitle,
            'quantity' => $this->quantity,
        ];
    }
}
