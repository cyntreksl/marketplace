<?php

namespace App\Jobs;

use App\Models\Auction;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CloseAuction implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $auctionId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            $auction = Auction::query()->with('bids')->lockForUpdate()->findOrFail($this->auctionId);

            if ($auction->status !== 'live' || $auction->ends_at->isFuture()) {
                return;
            }

            $winningBid = $auction->bids->sortByDesc(fn ($bid) => $bid->maximum_amount ?? $bid->amount)->first();
            $reserveMet = $winningBid !== null && ($auction->reserve_price === null || BigDecimal::of($auction->current_price ?? '0')->isGreaterThanOrEqualTo(BigDecimal::of($auction->reserve_price)));

            $auction->update([
                'status' => $reserveMet ? 'payment_pending' : 'ended_reserve_not_met',
                'winning_bid_id' => $reserveMet ? $winningBid->id : null,
                'payment_due_at' => $reserveMet ? now()->addHours(48) : null,
                'closed_at' => now(),
            ]);
        }, attempts: 3);
    }
}
