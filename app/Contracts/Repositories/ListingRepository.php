<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;

interface ListingRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function paginatePublic(array $filters, int $perPage = 18): LengthAwarePaginator;

    public function findPublicBySlug(string $slug): Listing;
}
