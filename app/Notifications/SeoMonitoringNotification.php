<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeoMonitoringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param list<string> $issues */
    public function __construct(public string $check, public array $issues, public bool $recovered = false, public bool $test = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->test) {
            return (new MailMessage)->subject('ProDeals SEO: monitoring email delivery test')
                ->greeting('ProDeals catalog monitoring')
                ->line('This is the one-time email delivery test requested during monitoring setup.')
                ->line('Daily checks will email actionable failures, material changes, weekly unresolved reminders and recovery notices. Routine successful checks remain silent.');
        }

        $message = (new MailMessage)
            ->subject('ProDeals SEO: '.$this->check.($this->recovered ? ' recovered' : ' needs attention'))
            ->greeting('ProDeals catalog monitoring')
            ->line($this->recovered ? 'The previously reported check is healthy again.' : 'The scheduled '.$this->check.' check found issues that need attention.');

        foreach ($this->issues as $issue) {
            $message->line($issue);
        }

        return $message->line('Checked at '.now()->timezone('Asia/Colombo')->format('Y-m-d H:i T'))
            ->action('Open Merchant Center', 'https://merchants.google.com/mc/products/sources/detail?a='.config('seo-monitoring.merchant.account_id').'&afmDataSourceId='.config('seo-monitoring.merchant.source_id'));
    }
}
