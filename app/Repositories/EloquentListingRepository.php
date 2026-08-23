<?php

namespace App\Repositories;

use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentListingRepository implements ListingRepository
{
    public function paginatePublic(array $filters, int $perPage = 18): LengthAwarePaginator
    {
        return Listing::query()
            ->publiclyVisible()
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'media:id,listing_id,path,type,sort_order',
                'sellerProfile:id,store_name,slug',
                'auction:id,listing_id,status,current_price,ends_at',
            ])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->whereHas('category', fn ($query) => $query->where('slug', $category)))
            ->when($filters['brand'] ?? null, fn ($query, string $brand) => $query->whereHas('brand', fn ($query) => $query->where('slug', $brand)))
            ->when($filters['condition'] ?? null, fn ($query, string $condition) => $query->where('condition', $condition))
            ->when($filters['listing_type'] ?? null, fn ($query, string $listingType) => $query->where('listing_type', $listingType))
            ->when($filters['location'] ?? null, fn ($query, string $location) => $query->where('location', $location))
            ->when($filters['min_price'] ?? null, fn ($query, string $minimum) => $query->where('price', '>=', $minimum))
            ->when($filters['max_price'] ?? null, fn ($query, string $maximum) => $query->where('price', '<=', $maximum))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicBySlug(string $slug): Listing
    {
        return Listing::query()
            ->publiclyVisible()
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'media:id,listing_id,path,type,sort_order',
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
}
