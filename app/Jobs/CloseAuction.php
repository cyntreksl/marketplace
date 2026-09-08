<?php

namespace App\Jobs;

use App\Services\AuctionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CloseAuction implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $auctionId) {}

    /**
     * Execute the job.
     */
    public function handle(AuctionLifecycleService $auctions): void
    {
        $auctions->close($this->auctionId);
    }
}
