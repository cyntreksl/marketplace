<?php

namespace App\Notifications;

use App\Models\AuctionOffer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public readonly AuctionOffer $offer)
    {
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
        $name = $notifiable instanceof User ? $notifiable->name : 'there';

        return (new MailMessage)
            ->subject('Your auction offer is ready')
            ->greeting("Hello {$name},")
            ->line("You can now purchase {$this->offer->auction->listing->title} at your winning bid of LKR {$this->offer->unit_price} per unit.")
            ->line("This offer covers {$this->offer->quantity} unit(s) and expires in 24 hours.")
            ->action('Review and pay', route('buyer.auction-offers.show', $this->offer))
            ->line('Auction offers must be paid by card. Cash on delivery and bank transfer are unavailable.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'auction_offer_id' => $this->offer->id,
            'auction_id' => $this->offer->auction_id,
            'expires_at' => $this->offer->expires_at?->toIso8601String(),
        ];
    }
}
