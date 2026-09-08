<?php

namespace App\Services;

use App\AuctionStatus;
use App\AuctionType;
use App\Contracts\Repositories\AuctionRepository;
use App\Exceptions\InvalidAuctionBidException;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class PlaceBidService
{
    public function __construct(
        private AuctionRepository $auctions,
        private MarketplaceSettingsService $settings,
    ) {}

    public function place(User $buyer, int $auctionId, string $amount): Bid
    {
        return DB::transaction(function () use ($buyer, $auctionId, $amount): Bid {
            $auction = $this->auctions->findForUpdate($auctionId);

            $this->ensureBuyerCanBid($buyer, $auction);

            $minimumBid = $this->minimumBid($auction, $buyer);
            if (BigDecimal::of($amount)->isLessThan($minimumBid)) {
                throw new InvalidAuctionBidException("Your bid must be at least {$minimumBid} per unit.");
            }

            $bid = $this->auctions->createBid($auction, $buyer, [
                'amount' => $amount,
                'maximum_amount' => null,
                'is_proxy' => false,
            ]);

            if ($auction->type !== AuctionType::Blind) {
                $this->auctions->save($auction, ['current_price' => $amount]);
            }

            $this->extendAuctionWhenEndingSoon($auction);

            return $bid;
        }, attempts: 3);
    }

    private function ensureBuyerCanBid(User $buyer, Auction $auction): void
    {
        if (! $this->settings->auctionTypeEnabled($auction->type)) {
            throw new InvalidAuctionBidException('Bidding is currently disabled for this auction type.');
        }

        if ($auction->status !== AuctionStatus::Live || $auction->starts_at->isFuture() || $auction->ends_at->lessThanOrEqualTo(now())) {
            throw new InvalidAuctionBidException('This auction is not accepting bids.');
        }

        if (! $buyer->is_active || ! $buyer->hasVerifiedEmail()) {
            throw new InvalidAuctionBidException('Verify an active account before placing a bid.');
        }

        if ($auction->listing->sellerProfile->user_id === $buyer->id) {
            throw new InvalidAuctionBidException('Sellers cannot bid on their own auctions.');
        }
    }

    private function minimumBid(Auction $auction, User $buyer): BigDecimal
    {
        if ($auction->type === AuctionType::Blind) {
            $ownHighestBid = $auction->bids
                ->where('buyer_id', $buyer->id)
                ->sortByDesc('amount')
                ->first();

            return $ownHighestBid === null
                ? BigDecimal::of($auction->starting_price)
                : BigDecimal::of($ownHighestBid->amount)->plus($auction->minimum_increment);
        }

        return $auction->current_price === null
            ? BigDecimal::of($auction->starting_price)
            : BigDecimal::of($auction->current_price)->plus($auction->minimum_increment);
    }

    private function extendAuctionWhenEndingSoon(Auction $auction): void
    {
        if ($auction->type !== AuctionType::TimeExtended) {
            return;
        }

        $extensionMinutes = $auction->extension_window_minutes;

        if ($auction->ends_at->lessThanOrEqualTo(now()->addMinutes($extensionMinutes))) {
            $this->auctions->save($auction, ['ends_at' => now()->addMinutes($extensionMinutes)]);
        }
    }
}
