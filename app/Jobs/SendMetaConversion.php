<?php

namespace App\Jobs;

use App\Contracts\MetaConversionsGateway;
use App\Support\MetaConversionEvent;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMetaConversion implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 15;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public int $uniqueFor = 604800;

    public function __construct(public MetaConversionEvent $event) {}

    public function uniqueId(): string
    {
        return $this->event->id;
    }

    public function handle(MetaConversionsGateway $gateway): void
    {
        $gateway->send($this->event);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Meta conversion event permanently failed.', [
            'event_name' => $this->event->name,
            'event_id' => $this->event->id,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
