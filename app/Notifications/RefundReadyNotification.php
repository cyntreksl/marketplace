<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly int $returnRequestId,
        public readonly string $itemTitle,
        public readonly string $amount,
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
            ->subject('Return ready for coordination: '.$this->itemTitle)
            ->greeting("Hello {$notifiable->name},")
            ->line("Return #{$this->returnRequestId} for {$this->itemTitle} was approved by the seller.")
            ->line("The calculated refund is LKR {$this->amount}. Please coordinate the offline return before marking it ready for refund.")
            ->action('Open returns queue', route('admin.returns.index'));
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
            'amount' => $this->amount,
        ];
    }
}
