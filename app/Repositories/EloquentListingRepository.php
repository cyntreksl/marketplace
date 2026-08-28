<?php

namespace App\Repositories;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentListingRepository implements ListingRepository
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function paginatePublic(array $filters, int $perPage = 18): LengthAwarePaginator
    {
        $effectivePrice = 'CAST(COALESCE(auctions.current_price, listings.sale_price, listings.price) AS DECIMAL(12, 2))';

        $query = $this->publicQuery()
            ->leftJoin('auctions', 'auctions.listing_id', '=', 'listings.id')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('listings.title', 'like', "%{$search}%"))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->whereIn('listings.category_id', $this->catalog->activeDescendantIdsForSlug($category)))
            ->when($filters['brand'] ?? null, fn ($query, string $brand) => $query->whereHas('brand', fn ($query) => $query->where('slug', $brand)))
            ->when($filters['condition'] ?? null, fn ($query, string $condition) => $query->where('listings.condition', $condition))
            ->when($filters['listing_type'] ?? null, fn ($query, string $listingType) => $query->where('listings.listing_type', $listingType))
            ->when($filters['location'] ?? null, fn ($query, string $location) => $query->where('listings.location', 'like', "%{$location}%"))
            ->when($filters['min_price'] ?? null, fn ($query, int|float|string $minimum) => $query->whereRaw("{$effectivePrice} >= ?", [$minimum]))
            ->when($filters['max_price'] ?? null, fn ($query, int|float|string $maximum) => $query->whereRaw("{$effectivePrice} <= ?", [$maximum]));

        $this->applySort($query, (string) ($filters['sort'] ?? 'newest'));

        return $query->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicBySlug(string $slug): Listing
    {
        return $this->publicQuery()
            ->with([
                'sellerProfile.user:id,name',
                'auction.bids.buyer:id,name',
            ])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function homepageBestOffers(int $limit = 8): Collection
    {
        return $this->publicQuery()
            ->where('listings.is_best_offer', true)
            ->where('listings.listing_type', 'buy_now')
            ->whereNotNull('listings.sale_price')
            ->whereColumn('listings.sale_price', '<', 'listings.price')
            ->latest('listings.created_at')
            ->limit($limit)
            ->get();
    }

    public function homepageNewArrivals(int $limit = 8): Collection
    {
        return $this->publicQuery()
            ->where('listings.is_new_arrival', true)
            ->latest('listings.created_at')
            ->limit($limit)
            ->get();
    }

    public function homepageForCategory(string $categorySlug, int $limit = 6): Collection
    {
        return $this->publicQuery()
            ->whereIn('listings.category_id', $this->catalog->activeDescendantIdsForSlug($categorySlug))
            ->latest('listings.created_at')
            ->limit($limit)
            ->get();
    }

    public function findPublicByIds(array $listingIds): Collection
    {
        if ($listingIds === []) {
            return collect();
        }

        $positions = array_flip($listingIds);

        return $this->publicQuery()
            ->whereIn('listings.id', $listingIds)
            ->get()
            ->sortBy(fn (Listing $listing): int => $positions[(int) $listing->getKey()])
            ->values();
    }

    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Listing::query()
            ->with(['category:id,name', 'sellerProfile:id,store_name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function updateMerchandising(Listing $listing, bool $isBestOffer, bool $isNewArrival): Listing
    {
        $listing->forceFill([
            'is_best_offer' => $isBestOffer,
            'is_new_arrival' => $isNewArrival,
        ])->save();

        return $listing;
    }

    public function paginateForSeller(SellerProfile $seller, int $perPage = 15): LengthAwarePaginator
    {
        return $seller->listings()
            ->with(['category:id,name', 'auction:id,listing_id,status,starts_at,ends_at'])
            ->withExists(['orderItems as has_orders'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function save(Listing $listing): Listing
    {
        $listing->save();

        return $listing;
    }

    public function findForSellerOrFail(SellerProfile $seller, int $listingId, bool $lockForUpdate = false): Listing
    {
        return $seller->listings()
            ->with(['category:id,name,slug,commission_percentage', 'brand:id,name,slug', 'auction'])
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->findOrFail($listingId);
    }

    public function delete(Listing $listing): void
    {
        $listing->delete();
    }

    /** @return Builder<Listing> */
    private function publicQuery(): Builder
    {
        return Listing::query()
            ->select('listings.*')
            ->publiclyVisible()
            ->withAvg('reviews as rating_average', 'rating')
            ->withCount('reviews')
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'media:id,listing_id,disk,path,type,sort_order',
                'sellerProfile:id,store_name,slug',
                'auction:id,listing_id,status,current_price,ends_at',
            ]);
    }

    /** @param Builder<Listing> $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderByRaw('CAST(COALESCE(auctions.current_price, listings.sale_price, listings.price) AS DECIMAL(12, 2)) asc'),
            'price_desc' => $query->orderByRaw('CAST(COALESCE(auctions.current_price, listings.sale_price, listings.price) AS DECIMAL(12, 2)) desc'),
            default => $query->latest('listings.created_at'),
        };
    }
}
