<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Pagination\LengthAwarePaginator;

interface ListingRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function paginatePublic(array $filters, int $perPage = 18): LengthAwarePaginator;

    public function findPublicBySlug(string $slug): Listing;

    /** @return LengthAwarePaginator<int, Listing> */
    public function paginateForSeller(SellerProfile $seller, int $perPage = 15): LengthAwarePaginator;

    public function save(Listing $listing): Listing;

    public function findForSellerOrFail(SellerProfile $seller, int $listingId, bool $lockForUpdate = false): Listing;

    public function delete(Listing $listing): void;
}
