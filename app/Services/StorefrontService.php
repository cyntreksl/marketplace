<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Support\Collection;

class StorefrontService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function homeData(): array
    {
        return [
            'featuredListings' => $this->listings->paginatePublic([], 6)->through(fn (Listing $listing) => $this->listingData($listing)),
            'categories' => $this->storefrontCategories(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function browseData(array $filters): array
    {
        $selectedCategorySlug = $filters['category'] ?? null;

        return [
            'filters' => $filters,
            'listings' => $this->listings->paginatePublic($filters)->through(fn (Listing $listing) => $this->listingData($listing)),
            'categories' => $this->storefrontCategories(),
            'categoryContext' => is_string($selectedCategorySlug)
                ? $this->catalog->activeCategoryContextBySlug($selectedCategorySlug)
                : null,
            'filterOptions' => [
                'brands' => $this->catalog->availableBrands()
                    ->map(fn (Brand $brand): array => $brand->only(['id', 'name', 'slug']))
                    ->values(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function listingDetailsData(string $slug): array
    {
        $listing = $this->listings->findPublicBySlug($slug);

        return [
            'listing' => $this->listingData($listing, detailed: true),
            'categories' => $this->storefrontCategories(),
            'categoryTrail' => $listing->category === null
                ? []
                : $this->catalog->activeCategoryTrailBySlug($listing->category->slug),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, slug: string, children: array<int, array{id: int, name: string, slug: string}>}>
     */
    private function storefrontCategories(): Collection
    {
        return $this->catalog->activeTopLevelCategories()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => $category->children
                    ->map(fn (Category $child): array => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                    ])
                    ->values()
                    ->all(),
            ]);
    }

    /** @return array<string, mixed> */
    private function listingData(Listing $listing, bool $detailed = false): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'description' => $detailed ? $listing->description : null,
            'condition' => $listing->condition,
            'listingType' => $listing->listing_type,
            'price' => $listing->sale_price ?? $listing->price,
            'location' => $listing->location,
            'warranty' => $listing->warranty,
            'stockQuantity' => $listing->stock_quantity - $listing->reserved_quantity,
            'category' => $listing->category?->only(['name', 'slug']),
            'brand' => $listing->brand?->only(['name', 'slug']),
            'media' => $listing->media->map(fn ($media) => [
                'path' => $media->path,
                'type' => $media->type,
                'url' => $media->url,
            ]),
            'seller' => $listing->sellerProfile?->only(['store_name', 'slug']),
            'auction' => $listing->auction === null ? null : [
                'id' => $listing->auction->id,
                'status' => $listing->auction->status,
                'currentPrice' => $listing->auction->current_price,
                'minimumIncrement' => $listing->auction->minimum_increment,
                'endsAt' => $listing->auction->ends_at->toIso8601String(),
                'bidCount' => $detailed ? $listing->auction->bids->count() : null,
            ],
        ];
    }
}
