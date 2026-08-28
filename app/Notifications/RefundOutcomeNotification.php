<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class RefundOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly int $returnRequestId,
        public readonly string $itemTitle,
        public readonly string $amount,
        public readonly string $status,
        public readonly ?string $failureDetails = null,
        public readonly bool $operations = false,
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
        $message = (new MailMessage)
            ->subject('Refund '.Str::headline($this->status).': '.$this->itemTitle)
            ->greeting("Hello {$notifiable->name},")
            ->line("The LKR {$this->amount} refund for {$this->itemTitle} has {$this->status}.");

        if ($this->failureDetails !== null) {
            $message->line('Details: '.$this->failureDetails);
        }

        $message->action(
            $this->operations ? 'Open returns queue' : 'View return status',
            $this->operations ? route('admin.returns.index') : route('buyer.returns.index'),
        );

        if ($this->status === 'failed' && ! $this->operations) {
            $message->line('Reply to this email if you need help with your refund.');
        }

        return $message;
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
            'status' => $this->status,
        ];
    }
}
