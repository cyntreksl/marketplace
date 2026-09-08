<?php

namespace App\Contracts\Repositories;

use App\Models\Auction;
use App\Models\AuctionOffer;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuctionRepository
{
    public function findForUpdate(int $auctionId): Auction;

    public function findForSellerOrFail(User $seller, int $auctionId, bool $lockForUpdate = false): Auction;

    public function findForAdminOrFail(int $auctionId, bool $lockForUpdate = false): Auction;

    public function listingForSellerOrFail(User $seller, int $listingId, bool $lockForUpdate = false): Listing;

    public function listingForUpdate(int $listingId): Listing;

    public function hasNonTerminalAuction(int $listingId, ?int $exceptAuctionId = null): bool;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Auction;

    /** @param array<string, mixed> $attributes */
    public function save(Auction $auction, array $attributes): Auction;

    public function delete(Auction $auction): void;

    public function reserveInventory(Auction $auction): void;

    public function releaseInventory(Auction $auction): void;

    /** @param array<string, mixed> $attributes */
    public function createBid(Auction $auction, User $buyer, array $attributes): Bid;

    /** @param array<string, mixed> $attributes */
    public function createOffer(Auction $auction, Bid $bid, array $attributes): AuctionOffer;

    public function offerForBuyerOrFail(User $buyer, int $offerId, bool $lockForUpdate = false): AuctionOffer;

    public function offerForUpdate(int $offerId): AuctionOffer;

    public function findOffer(int $offerId): ?AuctionOffer;

    /** @param array<string, mixed> $attributes */
    public function saveOffer(AuctionOffer $offer, array $attributes): AuctionOffer;

    /** @return Collection<int, Bid> */
    public function rankedDistinctBids(Auction $auction): Collection;

    public function nextWaitingOffer(Auction $auction): ?AuctionOffer;

    /** @return Collection<int, int> */
    public function activatableIds(): Collection;

    /** @return Collection<int, int> */
    public function closableIds(): Collection;

    /** @return Collection<int, int> */
    public function expiringOfferIds(): Collection;

    /** @return LengthAwarePaginator<int, Auction> */
    public function paginateForSeller(User $seller): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Auction> */
    public function paginateForAdmin(): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, AuctionOffer> */
    public function paginateOffersForBuyer(User $buyer): LengthAwarePaginator;

    /** @return Collection<int, Listing> */
    public function eligibleListingsForSeller(User $seller): Collection;
}
