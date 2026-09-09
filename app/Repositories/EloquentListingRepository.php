<?php

namespace App\Repositories;

use App\AuctionStatus;
use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class EloquentListingRepository implements ListingRepository
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function slugExists(string $slug, ?int $exceptListingId = null): bool
    {
        return Listing::query()->where('slug', $slug)
            ->when($exceptListingId, fn (Builder $query, int $listingId) => $query->whereKeyNot($listingId))
            ->exists();
    }

    public function paginatePublic(array $filters, string $channel = 'retail', int $perPage = 18): LengthAwarePaginator
    {
        if (($filters['listing_type'] ?? null) === 'auction') {
            $channel = 'auction';
        }
        $effectivePrice = match ($channel) {
            'wholesale' => 'CAST(listings.wholesale_price AS DECIMAL(12, 2))',
            'auction' => 'CAST(COALESCE(auctions.current_price, auctions.starting_price) AS DECIMAL(12, 2))',
            default => 'CAST(CASE WHEN listings.is_retail_enabled = 1 THEN COALESCE(listings.sale_price, listings.price) ELSE COALESCE(auctions.current_price, auctions.starting_price) END AS DECIMAL(12, 2))',
        };

        $query = $this->publicQuery($channel)
            ->leftJoin('auctions', function ($join): void {
                $join->on('auctions.listing_id', '=', 'listings.id')
                    ->where('auctions.status', '=', AuctionStatus::Live->value)
                    ->whereNull('auctions.deleted_at');
            })
            ->when($filters['collection'] ?? null, function (Builder $query, string $collection): void {
                match ($collection) {
                    'featured' => $query->where('listings.is_featured', true),
                    'deals' => $query->where('listings.is_best_offer', true)->whereNotNull('listings.sale_price')->whereColumn('listings.sale_price', '<', 'listings.price'),
                    'best-sellers' => $query->where('listings.is_best_seller', true),
                    'new-arrivals' => $query->where('listings.is_new_arrival', true),
                    'clearance' => $query->where('listings.is_clearance', true)->whereNotNull('listings.sale_price')->whereColumn('listings.sale_price', '<', 'listings.price'),
                    default => null,
                };
            })
            ->when($filters['seller_id'] ?? null, fn ($query, int $sellerId) => $query->where('listings.seller_profile_id', $sellerId))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('listings.title', 'like', "%{$search}%"))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->whereIn('listings.category_id', $this->catalog->activeDescendantIdsForSlug($category)))
            ->when($filters['brand'] ?? null, fn ($query, string $brand) => $query->whereHas('brand', fn ($query) => $query->where('slug', $brand)))
            ->when($filters['condition'] ?? null, fn ($query, string $condition) => $query->where('listings.condition', $condition))
            ->when(($filters['listing_type'] ?? null) === 'buy_now', fn ($query) => $query->where('listings.is_retail_enabled', true))
            ->when($filters['location'] ?? null, fn ($query, string $location) => $query->where('listings.location', 'like', "%{$location}%"))
            ->when($filters['min_price'] ?? null, fn ($query, int|float|string $minimum) => $query->whereRaw("{$effectivePrice} >= ?", [$minimum]))
            ->when($filters['max_price'] ?? null, fn ($query, int|float|string $maximum) => $query->whereRaw("{$effectivePrice} <= ?", [$maximum]));

        $this->applySort($query, (string) ($filters['sort'] ?? 'newest'), $channel);

        return $query->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicBySlug(string $slug): Listing
    {
        return $this->directPublicQuery()
            ->with([
                'sellerProfile.user:id,name',
                'activeAuction.bids',
                'variantOptions.values',
                'wholesalePriceTiers',
                'variants.optionValues.option',
                'variants.image',
                'variants.wholesalePriceTiers',
            ])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function sitemapProductCount(): int
    {
        return Listing::query()->directlyVisible()->count();
    }

    public function retailProductCount(): int
    {
        return Listing::query()->retailVisible()->count();
    }

    public function wholesaleProductCount(): int
    {
        return Listing::query()->wholesaleVisible()->count();
    }

    public function indexableAuctionCount(): int
    {
        return Listing::query()
            ->directlyVisible()
            ->whereHas('auctions', fn (Builder $query): Builder => $query
                ->where('status', AuctionStatus::Live)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now()))
            ->count();
    }

    public function indexableCollectionSlugs(): array
    {
        $collections = ['featured', 'deals', 'best-sellers', 'new-arrivals', 'clearance'];

        return collect($collections)
            ->filter(function (string $collection): bool {
                $query = Listing::query()->retailVisible();

                match ($collection) {
                    'featured' => $query->where('listings.is_featured', true),
                    'deals' => $query->where('listings.is_best_offer', true)
                        ->where('listings.listing_type', 'buy_now')
                        ->whereNotNull('listings.sale_price')
                        ->whereColumn('listings.sale_price', '<', 'listings.price'),
                    'best-sellers' => $query->where('listings.is_best_seller', true),
                    'new-arrivals' => $query->where('listings.is_new_arrival', true),
                    'clearance' => $query->where('listings.is_clearance', true)
                        ->where('listings.listing_type', 'buy_now')
                        ->whereNotNull('listings.sale_price')
                        ->whereColumn('listings.sale_price', '<', 'listings.price'),
                    default => $query->whereRaw('1 = 0'),
                };

                return $query->exists();
            })
            ->values()
            ->all();
    }

    public function sitemapProducts(int $page, int $perPage): Collection
    {
        return Listing::query()
            ->select(['id', 'title', 'slug', 'updated_at'])
            ->directlyVisible()
            ->with(['media:id,listing_id,disk,path,type,sort_order,variant_version,variants,processing_status'])
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();
    }

    public function merchantProducts(): LazyCollection
    {
        return $this->directPublicQuery()
            ->where('listings.is_retail_enabled', true)
            ->where('listings.listing_type', 'buy_now')
            ->with([
                'variantOptions.values',
                'variants.optionValues.option',
                'variants.image',
            ])
            ->orderBy('listings.id')
            ->lazyById(column: 'listings.id', alias: 'id');
    }

    public function homepageBestOffers(int $limit = 18): Collection
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

    public function homepageNewArrivals(int $limit = 18): Collection
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
        $search = trim((string) ($filters['search'] ?? ''));
        $query = Listing::query()
            ->with([
                'auction',
                'brand:id,name,deleted_at',
                'category:id,name,google_product_category_id,is_active,is_taxonomy_available,deleted_at',
                'media:id,listing_id,disk,path,type,sort_order,variant_version,variants,processing_status',
                'sellerProfile:id,store_name',
                'variants:id,listing_id,gtin,mpn,selling_price,stock_quantity,reserved_quantity,is_active',
                'wholesalePriceTiers',
                'variants.wholesalePriceTiers',
            ])
            ->when($filters['review_only'] ?? false, fn (Builder $query): Builder => $query->whereIn('status', ['pending_review', 'changes_requested', 'rejected', 'suspended']))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('sellerProfile', fn (Builder $query): Builder => $query->where('store_name', 'like', "%{$search}%"));
            }))
            ->when(($filters['status'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(($filters['listing_type'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('listing_type', $filters['listing_type']))
            ->when(($filters['product_type'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('product_type', $filters['product_type']))
            ->when(($filters['condition'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('condition', $filters['condition']));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title')->orderBy('id'),
            default => $query->latest(),
        };

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    public function updateMerchandising(Listing $listing, array $placements): Listing
    {
        $listing->forceFill($placements)->save();

        return $listing;
    }

    public function featuredDeals(int $limit = 18): Collection
    {
        return $this->publicQuery()->where('listings.is_featured', true)->latest('listings.created_at')->limit($limit)->get();
    }

    public function bestSellers(int $limit = 10): Collection
    {
        return $this->publicQuery()->where('listings.is_best_seller', true)->latest('listings.created_at')->limit($limit)->get();
    }

    public function clearance(int $limit = 10): Collection
    {
        return $this->publicQuery()
            ->where('listings.is_clearance', true)
            ->where('listings.listing_type', 'buy_now')
            ->whereNotNull('listings.sale_price')
            ->whereColumn('listings.sale_price', '<', 'listings.price')
            ->latest('listings.created_at')->limit($limit)->get();
    }

    public function related(Listing $listing, string $channel = 'retail', int $limit = 6): Collection
    {
        return $this->publicQuery($channel)
            ->whereKeyNot($listing->id)
            ->where(function (Builder $query) use ($listing): void {
                $query->where('listings.category_id', $listing->category_id)
                    ->when($listing->brand_id !== null, fn (Builder $brandQuery) => $brandQuery->orWhere('listings.brand_id', $listing->brand_id));
            })
            ->orderByRaw('CASE WHEN listings.category_id = ? THEN 0 ELSE 1 END', [$listing->category_id])
            ->latest('listings.created_at')
            ->limit($limit)
            ->get();
    }

    public function otherListingsFromSeller(Listing $listing, string $channel = 'retail', int $limit = 6): Collection
    {
        return $this->publicQuery($channel)
            ->whereKeyNot($listing->id)
            ->where('listings.seller_profile_id', $listing->seller_profile_id)
            ->latest('listings.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{q?: string, status?: string, sort?: string}  $filters
     * @return LengthAwarePaginator<int, Listing>
     */
    public function paginateForSeller(SellerProfile $seller, array $filters = [], string $channel = 'retail', int $perPage = 20): LengthAwarePaginator
    {
        $query = $seller->listings()
            ->with([
                'auction:auctions.id,auctions.listing_id,auctions.status,auctions.starts_at,auctions.ends_at',
                'brand:id,name',
                'category:id,name',
            ])
            ->withExists(['orderItems as has_orders']);

        $query->where($channel === 'wholesale' ? 'is_wholesale_enabled' : 'is_retail_enabled', true);

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $query): Builder => $query->where('title', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };

        return $query->paginate($perPage)
            ->withQueryString();
    }

    public function save(Listing $listing): Listing
    {
        $listing->save();

        return $listing;
    }

    public function createMedia(Listing $listing, array $attributes): ListingMedia
    {
        return $listing->media()->create($attributes);
    }

    public function createVariantMedia(ListingVariant $variant, array $attributes): ListingMedia
    {
        return $variant->image()->create([
            ...$attributes,
            'listing_id' => $variant->listing_id,
        ]);
    }

    public function findMedia(int $mediaId): ?ListingMedia
    {
        return ListingMedia::query()->find($mediaId);
    }

    public function saveMedia(ListingMedia $media): ListingMedia
    {
        $media->save();

        return $media;
    }

    public function mediaForMigration(string $fallbackSourceDisk, string $destinationDisk): LazyCollection
    {
        return ListingMedia::query()
            ->when(
                $fallbackSourceDisk === $destinationDisk,
                fn (Builder $query): Builder => $query
                    ->whereNotNull('disk')
                    ->where('disk', '!=', '')
                    ->where('disk', '!=', $destinationDisk),
                fn (Builder $query): Builder => $query
                    ->where(fn (Builder $query): Builder => $query
                        ->whereNull('disk')
                        ->orWhere('disk', '!=', $destinationDisk)),
            )
            ->lazyById();
    }

    public function mediaCount(Listing $listing): int
    {
        return $listing->media()->count();
    }

    public function nextMediaSortOrder(Listing $listing): int
    {
        return (int) $listing->media()->max('sort_order') + 1;
    }

    public function mediaForListing(Listing $listing, array $mediaIds): Collection
    {
        return $listing->media()->whereKey($mediaIds)->get();
    }

    public function deleteMedia(ListingMedia $media): void
    {
        $media->delete();
    }

    public function findForSellerOrFail(SellerProfile $seller, int $listingId, bool $lockForUpdate = false): Listing
    {
        return $seller->listings()
            ->with(['category:id,name,slug,commission_percentage', 'brand:id,name,slug', 'auction'])
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->findOrFail($listingId);
    }

    public function findDetailedForSellerOrFail(SellerProfile $seller, int $listingId): Listing
    {
        return $seller->listings()
            ->with([
                'auction',
                'brand:id,name,slug',
                'category',
                'media',
                'variantOptions.values',
                'wholesalePriceTiers',
                'variants.image',
                'variants.optionValues.option',
                'variants.wholesalePriceTiers',
            ])
            ->findOrFail($listingId);
    }

    public function findForAdminOrFail(int $listingId, bool $lockForUpdate = false): Listing
    {
        return Listing::query()
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->findOrFail($listingId);
    }

    public function findDetailedForAdminOrFail(int $listingId): Listing
    {
        return Listing::query()
            ->with([
                'auction',
                'brand:id,name,slug',
                'category',
                'media',
                'sellerProfile:id,store_name,status',
                'variantOptions.values',
                'wholesalePriceTiers',
                'variants.image',
                'variants.optionValues.option',
                'variants.wholesalePriceTiers',
            ])
            ->findOrFail($listingId);
    }

    public function delete(Listing $listing): void
    {
        $listing->delete();
    }

    /** @return Builder<Listing> */
    private function publicQuery(string $channel = 'retail'): Builder
    {
        $query = Listing::query()
            ->select('listings.*')
            ->withAvg('reviews as rating_average', 'rating')
            ->withCount('reviews')
            ->with($this->publicRelations());

        if ($channel === 'wholesale') {
            $query->wholesaleVisible();
        } elseif ($channel === 'auction') {
            $query->directlyVisible()->whereHas('auctions', fn (Builder $query): Builder => $query
                ->where('status', AuctionStatus::Live)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now()));
        } else {
            $query->directlyVisible()->where(function (Builder $query): void {
                $query->where('listings.is_retail_enabled', true)
                    ->orWhereHas('auctions', fn (Builder $query): Builder => $query
                        ->where('status', AuctionStatus::Live)
                        ->where('starts_at', '<=', now())
                        ->where('ends_at', '>', now()));
            });
        }

        return $query;
    }

    /** @return Builder<Listing> */
    private function directPublicQuery(): Builder
    {
        return Listing::query()
            ->select('listings.*')
            ->directlyVisible()
            ->withAvg('reviews as rating_average', 'rating')
            ->withCount('reviews')
            ->with($this->publicRelations());
    }

    /** @return array<int, string> */
    private function publicRelations(): array
    {
        return [
            'brand:id,name,slug',
            'category:id,name,slug,google_product_category_id,return_window_days,cod_enabled',
            'media:id,listing_id,disk,path,type,sort_order,variant_version,variants,processing_status',
            'sellerProfile:id,store_name,slug',
            'activeAuction',
        ];
    }

    /** @param Builder<Listing> $query */
    private function applySort(Builder $query, string $sort, string $channel = 'retail'): void
    {
        $effectivePrice = match ($channel) {
            'wholesale' => 'CAST(listings.wholesale_price AS DECIMAL(12, 2))',
            'auction' => 'CAST(COALESCE(auctions.current_price, auctions.starting_price) AS DECIMAL(12, 2))',
            default => 'CAST(CASE WHEN listings.is_retail_enabled = 1 THEN COALESCE(listings.sale_price, listings.price) ELSE COALESCE(auctions.current_price, auctions.starting_price) END AS DECIMAL(12, 2))',
        };

        match ($sort) {
            'price_asc' => $query->orderByRaw("{$effectivePrice} asc")->orderBy('listings.id'),
            'price_desc' => $query->orderByRaw("{$effectivePrice} desc")->orderBy('listings.id'),
            default => $query->latest('listings.created_at')->orderBy('listings.id'),
        };
    }
}
