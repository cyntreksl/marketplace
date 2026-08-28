<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly int $returnRequestId,
        public readonly string $itemTitle,
        public readonly string $decision,
        public readonly string $reason,
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
            ->subject('Return request '.$this->decision.': '.$this->itemTitle)
            ->greeting("Hello {$notifiable->name},")
            ->line("The seller {$this->decision} your return request for {$this->itemTitle}.")
            ->line('Seller response: '.$this->reason)
            ->action('View return status', route('buyer.returns.index'))
            ->line($this->decision === 'rejected'
                ? 'Reply to this email if you have questions about the decision.'
                : 'Our support team will coordinate the return before the refund is processed.');
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
            'decision' => $this->decision,
        ];
    }
}
