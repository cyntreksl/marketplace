<?php

namespace App\Jobs;

use App\Services\AuctionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessAuctionLifecycle implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(AuctionLifecycleService $auctions): void
    {
        $auctions->process();
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('auction-lifecycle'))->expireAfter(120)];
    }
}
