<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\SellerProfile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface ListingRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function paginatePublic(array $filters, int $perPage = 18): LengthAwarePaginator;

    public function findPublicBySlug(string $slug): Listing;

    /** @return Collection<int, Listing> */
    public function homepageBestOffers(int $limit = 8): Collection;

    /** @return Collection<int, Listing> */
    public function homepageNewArrivals(int $limit = 8): Collection;

    /** @return Collection<int, Listing> */
    public function homepageForCategory(string $categorySlug, int $limit = 6): Collection;

    /** @param array<int, int> $listingIds
     * @return Collection<int, Listing>
     */
    public function findPublicByIds(array $listingIds): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function updateMerchandising(Listing $listing, bool $isBestOffer, bool $isNewArrival): Listing;

    /** @return LengthAwarePaginator<int, Listing> */
    public function paginateForSeller(SellerProfile $seller, int $perPage = 15): LengthAwarePaginator;

    public function save(Listing $listing): Listing;

    /** @param array<string, mixed> $attributes */
    public function createMedia(Listing $listing, array $attributes): ListingMedia;

    public function findMedia(int $mediaId): ?ListingMedia;

    public function saveMedia(ListingMedia $media): ListingMedia;

    /** @return LazyCollection<int, ListingMedia> */
    public function mediaForMigration(): LazyCollection;

    public function mediaCount(Listing $listing): int;

    public function nextMediaSortOrder(Listing $listing): int;

    public function findForSellerOrFail(SellerProfile $seller, int $listingId, bool $lockForUpdate = false): Listing;

    public function delete(Listing $listing): void;
}
