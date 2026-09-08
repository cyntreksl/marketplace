<?php

namespace App\Repositories;

use App\AuctionOfferStatus;
use App\AuctionStatus;
use App\Contracts\Repositories\AuctionRepository;
use App\Models\Auction;
use App\Models\AuctionOffer;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EloquentAuctionRepository implements AuctionRepository
{
    public function findForUpdate(int $auctionId): Auction
    {
        return Auction::query()
            ->with(['listing.sellerProfile', 'variant', 'bids', 'offers.order.payments'])
            ->lockForUpdate()
            ->findOrFail($auctionId);
    }

    public function findForSellerOrFail(User $seller, int $auctionId, bool $lockForUpdate = false): Auction
    {
        return Auction::query()
            ->whereHas('listing.sellerProfile', fn (Builder $query): Builder => $query->where('user_id', $seller->id))
            ->with(['listing.variants.optionValues.option', 'variant', 'offers.order.payments'])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->findOrFail($auctionId);
    }

    public function findForAdminOrFail(int $auctionId, bool $lockForUpdate = false): Auction
    {
        return Auction::query()
            ->with(['listing.sellerProfile', 'variant.optionValues.option', 'bids', 'offers.order.payments'])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->findOrFail($auctionId);
    }

    public function listingForSellerOrFail(User $seller, int $listingId, bool $lockForUpdate = false): Listing
    {
        return Listing::query()
            ->whereHas('sellerProfile', fn (Builder $query): Builder => $query->where('user_id', $seller->id))
            ->with(['variants.optionValues.option', 'activeAuction'])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->findOrFail($listingId);
    }

    public function listingForUpdate(int $listingId): Listing
    {
        return Listing::query()
            ->with(['variants.optionValues.option', 'activeAuction'])
            ->lockForUpdate()
            ->findOrFail($listingId);
    }

    public function hasNonTerminalAuction(int $listingId, ?int $exceptAuctionId = null): bool
    {
        return Auction::query()
            ->where('listing_id', $listingId)
            ->whereIn('status', AuctionStatus::nonTerminalValues())
            ->when($exceptAuctionId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptAuctionId))
            ->exists();
    }

    public function create(array $attributes): Auction
    {
        return Auction::query()->create($attributes);
    }

    public function save(Auction $auction, array $attributes): Auction
    {
        $auction->forceFill($attributes)->save();

        return $auction->refresh();
    }

    public function delete(Auction $auction): void
    {
        $auction->delete();
    }

    public function reserveInventory(Auction $auction): void
    {
        $listing = Listing::query()->lockForUpdate()->findOrFail($auction->listing_id);
        $variant = $auction->listing_variant_id === null
            ? null
            : ListingVariant::query()->lockForUpdate()->findOrFail($auction->listing_variant_id);
        $available = $variant?->availableQuantity() ?? max(0, $listing->stock_quantity - $listing->reserved_quantity);

        if (! $listing->allow_backorders && $available < $auction->quantity) {
            throw ValidationException::withMessages(['quantity' => 'There is not enough available inventory for this auction lot.']);
        }

        $listing->increment('reserved_quantity', $auction->quantity);
        $variant?->increment('reserved_quantity', $auction->quantity);
    }

    public function releaseInventory(Auction $auction): void
    {
        if ($auction->inventory_reserved_at === null || $auction->inventory_released_at !== null) {
            return;
        }

        Listing::withTrashed()->whereKey($auction->listing_id)->decrement('reserved_quantity', $auction->quantity);
        if ($auction->listing_variant_id !== null) {
            ListingVariant::query()->whereKey($auction->listing_variant_id)->decrement('reserved_quantity', $auction->quantity);
        }
    }

    public function createBid(Auction $auction, User $buyer, array $attributes): Bid
    {
        return $auction->bids()->create([...$attributes, 'buyer_id' => $buyer->id]);
    }

    public function createOffer(Auction $auction, Bid $bid, array $attributes): AuctionOffer
    {
        return $auction->offers()->create([
            ...$attributes,
            'bid_id' => $bid->id,
            'buyer_id' => $bid->buyer_id,
        ]);
    }

    public function offerForBuyerOrFail(User $buyer, int $offerId, bool $lockForUpdate = false): AuctionOffer
    {
        return AuctionOffer::query()
            ->where('buyer_id', $buyer->id)
            ->with(['auction.listing.sellerProfile', 'auction.variant.optionValues.option', 'order.payments'])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->findOrFail($offerId);
    }

    public function offerForUpdate(int $offerId): AuctionOffer
    {
        return AuctionOffer::query()
            ->with(['auction.listing.sellerProfile', 'auction.variant.optionValues.option', 'buyer', 'order.payments'])
            ->lockForUpdate()
            ->findOrFail($offerId);
    }

    public function findOffer(int $offerId): ?AuctionOffer
    {
        return AuctionOffer::query()->with(['auction', 'buyer', 'order.payments'])->find($offerId);
    }

    public function saveOffer(AuctionOffer $offer, array $attributes): AuctionOffer
    {
        $offer->forceFill($attributes)->save();

        return $offer->refresh();
    }

    public function rankedDistinctBids(Auction $auction): Collection
    {
        return Bid::query()
            ->where('auction_id', $auction->id)
            ->orderByDesc('amount')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->unique('buyer_id')
            ->values();
    }

    public function nextWaitingOffer(Auction $auction): ?AuctionOffer
    {
        return AuctionOffer::query()
            ->where('auction_id', $auction->id)
            ->where('status', AuctionOfferStatus::Waiting)
            ->orderBy('rank')
            ->lockForUpdate()
            ->first();
    }

    public function activatableIds(): Collection
    {
        return Auction::query()
            ->where('status', AuctionStatus::Scheduled)
            ->where('starts_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');
    }

    public function closableIds(): Collection
    {
        return Auction::query()
            ->where('status', AuctionStatus::Live)
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');
    }

    public function expiringOfferIds(): Collection
    {
        return AuctionOffer::query()
            ->whereIn('status', [AuctionOfferStatus::Offered, AuctionOfferStatus::Accepted])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');
    }

    public function paginateForSeller(User $seller): LengthAwarePaginator
    {
        return Auction::query()
            ->whereHas('listing.sellerProfile', fn (Builder $query): Builder => $query->where('user_id', $seller->id))
            ->with(['listing:id,title,slug', 'variant:id,sku', 'offers:id,auction_id,status'])
            ->latest()
            ->paginate(20);
    }

    public function paginateForAdmin(): LengthAwarePaginator
    {
        return Auction::query()
            ->with(['listing:id,title,slug,seller_profile_id', 'listing.sellerProfile:id,store_name', 'variant:id,sku'])
            ->latest()
            ->paginate(25);
    }

    public function paginateOffersForBuyer(User $buyer): LengthAwarePaginator
    {
        return AuctionOffer::query()
            ->where('buyer_id', $buyer->id)
            ->with(['auction.listing:id,title,slug', 'order:id,auction_offer_id,number,status'])
            ->orderByRaw("CASE status WHEN 'offered' THEN 1 WHEN 'accepted' THEN 2 WHEN 'paid' THEN 3 WHEN 'waiting' THEN 4 WHEN 'expired' THEN 5 ELSE 6 END")
            ->orderByDesc('offered_at')
            ->paginate(20);
    }

    public function eligibleListingsForSeller(User $seller): Collection
    {
        return Listing::query()
            ->whereHas('sellerProfile', fn (Builder $query): Builder => $query->where('user_id', $seller->id))
            ->whereIn('status', ['draft', 'changes_requested', 'rejected', 'pending_review', 'approved'])
            ->whereDoesntHave('auctions', fn (Builder $query): Builder => $query->whereIn('status', AuctionStatus::nonTerminalValues()))
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->with('optionValues.option')])
            ->orderBy('title')
            ->get(['id', 'title', 'product_type', 'stock_quantity', 'reserved_quantity', 'status']);
    }
}
