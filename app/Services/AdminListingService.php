<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminListingService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly ListingSeoScoreService $seoScores,
    ) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function moderationQueue(array $filters): LengthAwarePaginator
    {
        return $this->listings->paginateForAdmin([...$filters, 'review_only' => true])
            ->through(function (Listing $listing): Listing {
                $listing->setAttribute('seo_score', $this->seoScores->score($listing));

                return $listing;
            });
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function allProducts(array $filters): LengthAwarePaginator
    {
        return $this->listings->paginateForAdmin($filters)
            ->through(function (Listing $listing): Listing {
                $listing->setAttribute('seo_score', $this->seoScores->score($listing));

                return $listing;
            });
    }

    public function product(int $listingId): Listing
    {
        $listing = $this->listings->findDetailedForAdminOrFail($listingId);
        $listing->makeVisible(['cost_price', 'supplier_name', 'internal_notes']);
        $listing->variants->each->makeVisible('cost_price');
        $listing->setAttribute('seo_score', $this->seoScores->score($listing));

        return $listing;
    }

    /** @return array{sold: array{baseline: int, actual: int, total: int}, watchers: array{baseline: int, actual: int, total: int}, views: array{baseline: int, actual: int, total: int}} */
    public function engagement(Listing $listing): array
    {
        return $this->listings->engagement($listing);
    }
}
