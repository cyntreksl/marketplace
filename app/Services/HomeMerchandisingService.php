<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeMerchandisingService
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly ListingRepository $listings,
        private readonly AuditLogService $auditLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function adminData(array $filters = []): array
    {
        return [
            'selectedCategories' => $this->catalog->selectedHomepageCategories(),
            'listings' => $this->listings->paginateForAdmin($filters),
        ];
    }

    /**
     * @param  array<int, int>  $popularCategoryIds
     * @param  array<int, int>  $featuredCategoryIds
     */
    public function updateCategories(User $actor, array $popularCategoryIds, array $featuredCategoryIds, string $reason): void
    {
        DB::transaction(function () use ($actor, $popularCategoryIds, $featuredCategoryIds, $reason): void {
            $before = $this->catalog->selectedHomepageCategories()->keyBy('id');
            $this->catalog->replaceHomepageCategories($popularCategoryIds, $featuredCategoryIds);
            $after = $this->catalog->selectedHomepageCategories()->keyBy('id');

            $before->keys()->merge($after->keys())->unique()->each(function (int $categoryId) use ($actor, $before, $after, $reason): void {
                $category = $this->catalog->categoryWithTrashed($categoryId);
                $this->auditLogs->record(
                    $actor,
                    'homepage.category_merchandising_updated',
                    $category,
                    $before->get($category->id)?->getAttributes(),
                    $after->get($category->id)?->getAttributes() ?? $category->getAttributes(),
                    $reason,
                );
            });
        });
    }

    public function updateListing(User $actor, Listing $listing, bool $isBestOffer, bool $isNewArrival, string $reason): Listing
    {
        if ($isBestOffer && ! $this->isEligibleBestOffer($listing)) {
            throw ValidationException::withMessages([
                'is_best_offer' => 'Best Offers must be approved buy-now listings with a lower sale price.',
            ]);
        }

        return DB::transaction(function () use ($actor, $listing, $isBestOffer, $isNewArrival, $reason): Listing {
            $before = $listing->getAttributes();
            $listing = $this->listings->updateMerchandising($listing, $isBestOffer, $isNewArrival);
            $this->auditLogs->record($actor, 'listing.merchandising_updated', $listing, $before, $listing->getAttributes(), $reason);

            return $listing;
        });
    }

    private function isEligibleBestOffer(Listing $listing): bool
    {
        return $listing->status === 'approved'
            && $listing->listing_type === 'buy_now'
            && $listing->sale_price !== null
            && (float) $listing->sale_price < (float) $listing->price;
    }
}
