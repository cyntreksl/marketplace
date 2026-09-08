<?php

namespace App\Services;

use App\AuctionOfferStatus;
use App\AuctionStatus;
use App\Contracts\Repositories\AuctionRepository;
use App\Models\AuctionOffer;
use App\Models\Bid;
use App\Notifications\AuctionOfferNotification;
use Illuminate\Support\Facades\DB;

class AuctionLifecycleService
{
    public const PAYMENT_WINDOW_HOURS = 24;

    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly CheckoutPaymentService $payments,
    ) {}

    public function process(): void
    {
        foreach ($this->auctions->activatableIds() as $auctionId) {
            $this->activate((int) $auctionId);
        }

        foreach ($this->auctions->closableIds() as $auctionId) {
            $this->close((int) $auctionId);
        }

        foreach ($this->auctions->expiringOfferIds() as $offerId) {
            $this->expireOffer((int) $offerId);
        }
    }

    public function activate(int $auctionId): void
    {
        DB::transaction(function () use ($auctionId): void {
            $auction = $this->auctions->findForUpdate($auctionId);
            if ($auction->status !== AuctionStatus::Scheduled || $auction->starts_at->isFuture()) {
                return;
            }

            if ($auction->listing->status !== 'approved' || ! $auction->listing->is_active) {
                $this->auctions->releaseInventory($auction);
                $this->auctions->save($auction, [
                    'status' => AuctionStatus::Cancelled,
                    'cancellation_reason' => 'Product was unavailable when the auction was due to start.',
                    'inventory_released_at' => now(),
                    'closed_at' => now(),
                ]);

                return;
            }

            $this->auctions->save($auction, ['status' => AuctionStatus::Live]);
        }, attempts: 3);
    }

    public function close(int $auctionId): void
    {
        $activeOffer = DB::transaction(function () use ($auctionId): ?AuctionOffer {
            $auction = $this->auctions->findForUpdate($auctionId);
            if ($auction->status !== AuctionStatus::Live || $auction->ends_at->isFuture()) {
                return null;
            }

            $rankedBids = $this->auctions->rankedDistinctBids($auction);
            if ($rankedBids->isEmpty()) {
                $this->auctions->releaseInventory($auction);
                $this->auctions->save($auction, [
                    'status' => AuctionStatus::EndedNoBids,
                    'inventory_released_at' => now(),
                    'closed_at' => now(),
                ]);

                return null;
            }

            $offers = $rankedBids->map(function (Bid $bid, int $index) use ($auction): AuctionOffer {
                return $this->auctions->createOffer($auction, $bid, [
                    'rank' => $index + 1,
                    'unit_price' => $bid->amount,
                    'quantity' => $auction->quantity,
                    'status' => AuctionOfferStatus::Waiting,
                ]);
            });
            $firstOffer = $offers->first();
            $firstOffer = $this->activateOffer($firstOffer);
            $this->auctions->save($auction, [
                'status' => AuctionStatus::OfferPending,
                'winning_bid_id' => $firstOffer->bid_id,
                'current_price' => $firstOffer->unit_price,
                'payment_due_at' => $firstOffer->expires_at,
                'closed_at' => now(),
            ]);

            return $firstOffer->load('buyer', 'auction.listing');
        }, attempts: 3);

        $this->notifyOffer($activeOffer);
    }

    public function expireOffer(int $offerId): void
    {
        $offer = $this->auctions->findOffer($offerId);
        if ($offer === null || ! in_array($offer->status, [AuctionOfferStatus::Offered, AuctionOfferStatus::Accepted], true) || $offer->expires_at?->isFuture()) {
            return;
        }

        if ($offer->order?->payments->contains(fn ($payment): bool => $payment->status === 'pending' && $payment->checkout_session_id !== null)) {
            $this->payments->refresh($offer->order);
        }

        $nextOffer = DB::transaction(function () use ($offerId): ?AuctionOffer {
            $offer = $this->auctions->offerForUpdate($offerId);
            if (! in_array($offer->status, [AuctionOfferStatus::Offered, AuctionOfferStatus::Accepted], true) || $offer->expires_at?->isFuture()) {
                return null;
            }

            if ($offer->order?->payments->contains('status', 'paid')) {
                return null;
            }

            $this->auctions->saveOffer($offer, [
                'status' => AuctionOfferStatus::Expired,
                'expired_at' => now(),
            ]);
            $auction = $this->auctions->findForUpdate($offer->auction_id);
            $next = $this->auctions->nextWaitingOffer($auction);
            if ($next === null) {
                $this->auctions->releaseInventory($auction);
                $this->auctions->save($auction, [
                    'status' => AuctionStatus::BidderExhausted,
                    'winning_bid_id' => null,
                    'payment_due_at' => null,
                    'inventory_released_at' => now(),
                ]);

                return null;
            }

            $next = $this->activateOffer($next);
            $this->auctions->save($auction, [
                'winning_bid_id' => $next->bid_id,
                'current_price' => $next->unit_price,
                'payment_due_at' => $next->expires_at,
            ]);

            return $next->load('buyer', 'auction.listing');
        }, attempts: 3);

        $this->notifyOffer($nextOffer);
    }

    private function activateOffer(AuctionOffer $offer): AuctionOffer
    {
        return $this->auctions->saveOffer($offer, [
            'status' => AuctionOfferStatus::Offered,
            'offered_at' => now(),
            'expires_at' => now()->addHours(self::PAYMENT_WINDOW_HOURS),
        ]);
    }

    private function notifyOffer(?AuctionOffer $offer): void
    {
        if ($offer !== null) {
            $offer->buyer->notify(new AuctionOfferNotification($offer));
        }
    }
}
