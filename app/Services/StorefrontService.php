<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\PromotionRepository;
use App\Contracts\Repositories\ReviewRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Support\Collection;

class StorefrontService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
        private readonly PromotionRepository $promotions,
        private readonly ReviewRepository $reviews,
        private readonly SeoHeadService $seo,
        private readonly StaticMediaService $staticMedia,
    ) {}

    /** @return array<string, mixed> */
    public function homeData(): array
    {
        return [
            'categories' => $this->storefrontCategories(),
            'promotions' => [
                'hero' => $this->promotionData('hero', 1, [
                    ['title' => 'Discover better deals, closer to home', 'imageUrl' => $this->staticMedia->url('images/storefront/hero-marketplace.jpg'), 'linkUrl' => '/listings'],
                ]),
                'secondary' => $this->promotionData('secondary', 2, [
                    ['title' => 'Refresh your everyday spaces', 'imageUrl' => $this->staticMedia->url('images/storefront/home-lifestyle.jpg'), 'linkUrl' => '/listings'],
                    ['title' => 'Technology that fits your day', 'imageUrl' => $this->staticMedia->url('images/storefront/technology.jpg'), 'linkUrl' => '/listings?category=electronics'],
                ]),
            ],
            'popularCategories' => $this->catalog->popularHomepageCategories()
                ->map(fn (Category $category): array => [
                    ...$category->only(['id', 'name', 'slug']),
                    'image_url' => $category->imageUrl(),
                ])
                ->values(),
            'bestOffers' => $this->listings->homepageBestOffers()->map(fn (Listing $listing): array => $this->listingData($listing))->values(),
            'newArrivals' => $this->listings->homepageNewArrivals()->map(fn (Listing $listing): array => $this->listingData($listing))->values(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function homepageCategorySections(): array
    {
        return $this->catalog->featuredHomepageCategories()
            ->map(fn (Category $category, int $index): array => [
                'category' => [
                    ...$category->only(['id', 'name', 'slug']),
                    'image_url' => $category->imageUrl(),
                    'banner_image_url' => $category->bannerImageUrl(),
                ],
                'variant' => ['image', 'tinted', 'clean'][$index % 3],
                'listings' => $this->listings->homepageForCategory($category->slug)
                    ->map(fn (Listing $listing): array => $this->listingData($listing))
                    ->values(),
            ])
            ->filter(fn (array $section): bool => $section['listings']->isNotEmpty())
            ->values()
            ->all();
    }

    /** @return array{summary: array{average: float|null, count: int}, reviews: array<int, array<string, mixed>>} */
    public function homepageSocialProof(): array
    {
        return [
            'summary' => $this->reviews->summary(),
            'reviews' => $this->reviews->recent(6)->map(fn ($review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'buyerName' => $review->buyer->name,
                'listingTitle' => $review->orderItem->title,
                'listingSlug' => $review->orderItem->listing?->slug,
                'createdAt' => $review->created_at->toDateString(),
            ])->values()->all(),
        ];
    }

    /** @param array<int, int> $listingIds
     * @return array<int, array<string, mixed>>
     */
    public function recentlyViewedData(array $listingIds): array
    {
        return $this->listings->findPublicByIds($listingIds)
            ->map(fn (Listing $listing): array => $this->listingData($listing))
            ->values()
            ->all();
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
            'head' => $this->seo->listing($listing),
            'listing' => $this->listingData($listing, detailed: true),
            'reviews' => $this->reviews->forListing((int) $listing->id, 20)->map(fn ($review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'buyerName' => $review->buyer->name,
                'createdAt' => $review->created_at->toDateString(),
            ])->values(),
            'categories' => $this->storefrontCategories(),
            'categoryTrail' => $listing->category === null
                ? []
                : $this->catalog->activeCategoryTrailBySlug($listing->category->slug),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function storefrontCategories(): Collection
    {
        return $this->catalog->activeTopLevelCategories()
            ->map(fn (Category $category): array => $this->storefrontCategoryData($category));
    }

    /** @return array<string, mixed> */
    private function storefrontCategoryData(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image_url' => $category->imageUrl(),
            'children' => $category->children
                ->map(fn (Category $child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'image_url' => $child->imageUrl(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function listingData(Listing $listing, bool $detailed = false): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'description' => $detailed ? $listing->description : null,
            'shortDescription' => $detailed ? $listing->short_description : null,
            'metaTitle' => $detailed ? $listing->meta_title : null,
            'metaDescription' => $detailed ? $listing->meta_description : null,
            'condition' => $listing->condition,
            'listingType' => $listing->listing_type,
            'price' => $listing->price,
            'salePrice' => $listing->sale_price,
            'effectivePrice' => $listing->auction === null ? $listing->buyNowPrice() : $listing->auction->current_price,
            'discountPercentage' => $this->discountPercentage($listing),
            'ratingAverage' => $listing->getAttribute('rating_average') === null ? null : round((float) $listing->getAttribute('rating_average'), 1),
            'reviewCount' => (int) $listing->getAttribute('reviews_count'),
            'location' => $listing->location,
            'warranty' => $listing->warranty,
            'stockQuantity' => $listing->stock_quantity - $listing->reserved_quantity,
            'stockStatus' => $listing->stockStatus(),
            'category' => $listing->category?->only(['name', 'slug']),
            'brand' => $listing->brand?->only(['name', 'slug']),
            'media' => $listing->media->map(fn ($media) => [
                'path' => $media->path,
                'type' => $media->type,
                'url' => $media->url,
                'thumbnailUrl' => $media->urlForVariant('thumbnail'),
                'cardUrl' => $media->urlForVariant('card'),
                'card2xUrl' => $media->urlForVariant('card_2x'),
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

    /**
     * @param  array<int, array{title: string, imageUrl: string, linkUrl: string}>  $fallbacks
     * @return array<int, array{id: int|null, title: string, imageUrl: string, linkUrl: string|null}>
     */
    private function promotionData(string $placement, int $limit, array $fallbacks): array
    {
        $promotions = $this->promotions->activeForPlacement($placement, $limit);

        if ($promotions->isEmpty()) {
            return collect($fallbacks)
                ->map(fn (array $promotion): array => ['id' => null, ...$promotion])
                ->all();
        }

        return $promotions->map(fn ($promotion): array => [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'imageUrl' => $promotion->imageUrl() ?? $this->staticMedia->url(
                $promotion->placement === 'hero'
                    ? 'images/storefront/hero-marketplace.jpg'
                    : 'images/storefront/home-lifestyle.jpg',
            ),
            'linkUrl' => $promotion->link_url,
        ])->values()->all();
    }

    private function discountPercentage(Listing $listing): ?int
    {
        if ($listing->listing_type !== 'buy_now' || $listing->sale_price === null || (float) $listing->price <= 0) {
            return null;
        }

        return (int) round((((float) $listing->price - (float) $listing->sale_price) / (float) $listing->price) * 100);
    }
}
