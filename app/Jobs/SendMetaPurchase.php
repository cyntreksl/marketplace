<?php

namespace App\Jobs;

use App\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMetaPurchase implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 15;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public int $uniqueFor = 604800;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return 'Purchase:'.$this->orderId;
    }

    public function handle(MetaConversionsService $conversions): void
    {
        $conversions->sendPurchase($this->orderId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Meta Purchase event permanently failed.', [
            'order_id' => $this->orderId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
