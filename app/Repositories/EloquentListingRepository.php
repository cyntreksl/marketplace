<?php

namespace App\Repositories;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentListingRepository implements ListingRepository
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function paginatePublic(array $filters, int $perPage = 18): LengthAwarePaginator
    {
        $effectivePrice = 'CAST(COALESCE(auctions.current_price, listings.sale_price, listings.price) AS DECIMAL(12, 2))';

        $query = Listing::query()
            ->select('listings.*')
            ->leftJoin('auctions', 'auctions.listing_id', '=', 'listings.id')
            ->publiclyVisible()
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'media:id,listing_id,disk,path,type,sort_order',
                'sellerProfile:id,store_name,slug',
                'auction:id,listing_id,status,current_price,ends_at',
            ])
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
        return Listing::query()
            ->publiclyVisible()
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'media:id,listing_id,disk,path,type,sort_order',
                'sellerProfile.user:id,name',
                'auction.bids.buyer:id,name',
            ])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function save(Listing $listing): Listing
    {
        $listing->save();

        return $listing;
    }

    public function findForSellerOrFail(SellerProfile $seller, int $listingId): Listing
    {
        return $seller->listings()
            ->with(['category:id,name,slug,commission_percentage', 'brand:id,name,slug', 'auction'])
            ->findOrFail($listingId);
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
