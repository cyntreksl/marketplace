<?php

namespace App\Services;

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

    public function place(User $buyer, int $auctionId, string $maximumAmount): Bid
    {
        return DB::transaction(function () use ($buyer, $auctionId, $maximumAmount): Bid {
            $auction = $this->auctions->findForUpdate($auctionId);

            $this->ensureBuyerCanBid($buyer, $auction);

            $minimumBid = BigDecimal::of($auction->current_price ?? $auction->starting_price)
                ->plus($auction->minimum_increment);

            if (BigDecimal::of($maximumAmount)->isLessThan($minimumBid)) {
                throw new InvalidAuctionBidException("Your maximum bid must be at least {$minimumBid}.");
            }

            $bid = $this->recordBid($auction, $buyer, $maximumAmount, $minimumBid);

            $this->extendAuctionWhenEndingSoon($auction);

            return $bid;
        }, attempts: 3);
    }

    private function ensureBuyerCanBid(User $buyer, Auction $auction): void
    {
        if ($auction->status !== 'live' || $auction->starts_at->isFuture() || $auction->ends_at->isPast()) {
            throw new InvalidAuctionBidException('This auction is not accepting bids.');
        }

        if ($auction->listing->sellerProfile->user_id === $buyer->id) {
            throw new InvalidAuctionBidException('Sellers cannot bid on their own auctions.');
        }
    }

    private function recordBid(Auction $auction, User $buyer, string $maximumAmount, BigDecimal $minimumBid): Bid
    {
        $leadingBid = $auction->bids->sortByDesc(fn (Bid $bid) => $bid->maximum_amount ?? $bid->amount)->first();
        $leadingMaximum = $leadingBid === null ? null : (string) ($leadingBid->maximum_amount ?? $leadingBid->amount);

        $maximum = BigDecimal::of($maximumAmount);
        $amount = $leadingMaximum === null
            ? $minimumBid
            : ($maximum->isLessThan(BigDecimal::of($leadingMaximum)->plus($auction->minimum_increment))
                ? $maximum
                : BigDecimal::of($leadingMaximum)->plus($auction->minimum_increment));

        $bid = Bid::create([
            'auction_id' => $auction->id,
            'buyer_id' => $buyer->id,
            'amount' => (string) $amount,
            'maximum_amount' => $maximumAmount,
            'is_proxy' => $maximum->isGreaterThan($amount),
        ]);

        if ($leadingMaximum === null || $maximum->isGreaterThan(BigDecimal::of($leadingMaximum))) {
            $auction->update(['current_price' => (string) $amount]);
        }

        return $bid;
    }

    private function extendAuctionWhenEndingSoon(Auction $auction): void
    {
        $extensionMinutes = $this->settings->integer('auction.anti_sniping_extension_minutes', 5);

        if ($auction->ends_at->lessThanOrEqualTo(now()->addMinutes($extensionMinutes))) {
            $auction->update(['ends_at' => $auction->ends_at->addMinutes($extensionMinutes)]);
        }
    }
}
