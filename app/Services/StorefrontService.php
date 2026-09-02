<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\ProductQuestionRepository;
use App\Contracts\Repositories\PromotionRepository;
use App\Contracts\Repositories\ReviewRepository;
use App\Contracts\Repositories\WatchlistRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Models\User;
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
        private readonly ProductQuestionRepository $questions,
        private readonly WatchlistRepository $watchlists,
    ) {}

    /** @return array<string, mixed> */
    public function homeData(): array
    {
        return [
            'categories' => $this->storefrontCategories(),
            'promotions' => [
                'hero' => $this->promotionData('hero', 5, [
                    [
                        'title' => 'Upgrade your everyday essentials',
                        'subtitle' => 'Smart appliances and must-have tech at prices worth celebrating.',
                        'ctaLabel' => 'Shop the Sale',
                        'artworkAlt' => 'Kitchen appliances and personal technology featured in a limited-time sale',
                        'imageUrl' => $this->staticMedia->url('images/storefront/hero-home-appliances.webp'),
                        'linkUrl' => '/collections/deals',
                    ],
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
            'featuredDeals' => $this->listings->featuredDeals()->map(fn (Listing $listing): array => $this->listingData($listing))->values(),
            'bestSellers' => $this->listings->bestSellers()->map(fn (Listing $listing): array => $this->listingData($listing))->values(),
            'newArrivals' => $this->listings->homepageNewArrivals()->map(fn (Listing $listing): array => $this->listingData($listing))->values(),
            'topBrands' => $this->catalog->topBrands()->map(fn (Brand $brand): array => [
                ...$brand->only(['id', 'name', 'slug']),
                'logoUrl' => $brand->getAttribute('logo_url'),
            ])->values(),
            'flashSale' => $this->flashSaleData(),
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

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function categoryData(string $slug, array $filters): array
    {
        $category = $this->catalog->activeCategoryBySlug($slug);
        $data = $this->browseData([...$filters, 'category' => $slug]);
        $page = max(1, (int) request()->query('page', 1));
        $hasFilters = collect(request()->query())->except('page')->filter(fn (mixed $value): bool => filled($value))->isNotEmpty();
        $canonical = route('categories.show', $slug).(! $hasFilters && $page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: $category->name.' in Sri Lanka - '.config('app.name'),
            description: 'Shop '.$category->name.' from trusted Sri Lankan sellers on '.config('app.name').'.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $category->name, 'url' => route('categories.show', $slug)],
            ],
            indexable: ! $hasFilters,
        );

        return [...$data, 'seo' => $seo, 'head' => $this->seo->tags($seo)];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function brandData(string $slug, array $filters): array
    {
        $brand = $this->catalog->activeBrandBySlug($slug);
        $data = $this->browseData([...$filters, 'brand' => $slug]);
        $page = max(1, (int) request()->query('page', 1));
        $hasFilters = collect(request()->query())->except('page')->filter(fn (mixed $value): bool => filled($value))->isNotEmpty();
        $canonical = route('brands.show', $slug).(! $hasFilters && $page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: $brand->name.' Products in Sri Lanka - '.config('app.name'),
            description: 'Shop '.$brand->name.' products from trusted Sri Lankan sellers on '.config('app.name').'.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Brands', 'url' => route('brands.index')],
                ['name' => $brand->name, 'url' => route('brands.show', $slug)],
            ],
            indexable: ! $hasFilters,
        );

        return [...$data, 'seo' => $seo, 'head' => $this->seo->tags($seo)];
    }

    /** @return array<string, mixed> */
    public function listingDetailsData(string $slug, ?User $viewer = null, ?int $requestedVariantId = null): array
    {
        $listing = $this->listings->findPublicBySlug($slug);
        $categoryTrail = $listing->category === null
            ? []
            : $this->catalog->activeCategoryTrailBySlug($listing->category->slug);
        $seo = $this->seo->listingPayload($listing, $categoryTrail);
        $selectedVariantId = $listing->variants
            ->where('is_active', true)
            ->firstWhere('id', $requestedVariantId)?->id;

        return [
            'head' => $this->seo->tags($seo),
            'seo' => $seo,
            'listing' => $this->listingData($listing, detailed: true),
            'selectedVariantId' => $selectedVariantId,
            'reviews' => $this->reviews->forListing((int) $listing->id, 20)->map(fn ($review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'buyerName' => $review->buyer->name,
                'createdAt' => $review->created_at->toDateString(),
            ])->values(),
            'categories' => $this->storefrontCategories(),
            'categoryTrail' => $categoryTrail,
            'questions' => $this->questions->answeredFor($listing)->map(fn ($question): array => $this->questionData($question))->values(),
            'pendingQuestions' => $this->questions->pendingForViewer($listing, $viewer)->map(fn ($question): array => $this->questionData($question))->values(),
            'isWishlisted' => $viewer === null ? false : $this->watchlists->contains($viewer, $listing),
            'activeCampaign' => $this->activeCampaignFor($listing),
            'categoryPolicies' => $listing->category === null ? null : [
                'returnWindowDays' => $listing->category->return_window_days,
                'codEnabled' => $listing->category->cod_enabled,
            ],
            'relatedListings' => $this->listings->related($listing)->map(fn (Listing $related): array => $this->listingData($related))->values(),
        ];
    }

    /**
     * @param  array<int, int>  $listingIds
     * @return array<int, array<string, mixed>>
     */
    public function comparisonData(array $listingIds): array
    {
        return $this->listings->findPublicByIds($listingIds)
            ->map(fn (Listing $listing): array => $this->listingData($listing, detailed: true))
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function navigationCategories(): Collection
    {
        return $this->storefrontCategories();
    }

    /** @return array<string, mixed> */
    public function cardData(Listing $listing): array
    {
        return $this->listingData($listing);
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
        $activeVariants = $detailed
            ? $listing->variants->where('is_active', true)
            : collect();

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'description' => $detailed ? $listing->description : null,
            'shortDescription' => $detailed ? $listing->short_description : null,
            'metaTitle' => $detailed ? $listing->meta_title : null,
            'metaDescription' => $detailed ? $listing->meta_description : null,
            'model' => $detailed ? $listing->model : null,
            'gtin' => $detailed ? $listing->gtin : null,
            'mpn' => $detailed ? $listing->mpn : null,
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
            'productType' => $listing->product_type,
            'specifications' => $detailed ? ($listing->specifications ?? []) : [],
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
            'variantOptions' => $detailed
                ? $listing->variantOptions->map(fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'values' => $option->values
                        ->filter(fn ($value): bool => $activeVariants->contains(
                            fn ($variant): bool => $variant->optionValues->contains('id', $value->id),
                        ))
                        ->pluck('value')
                        ->values(),
                ])->values()
                : [],
            'variants' => $detailed
                ? $activeVariants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'gtin' => $variant->gtin,
                    'mpn' => $variant->mpn,
                    'sellingPrice' => $variant->selling_price,
                    'marketPrice' => $variant->market_price,
                    'selectionKey' => $variant->combination_key,
                    'selections' => $variant->optionValues->sortBy(fn ($value) => $value->option->position)->mapWithKeys(fn ($value): array => [$value->option->name => $value->value]),
                    'stockQuantity' => $variant->availableQuantity(),
                    'image' => $variant->image === null ? null : [
                        'thumbnailUrl' => $variant->image->urlForVariant('thumbnail'),
                        'cardUrl' => $variant->image->urlForVariant('card'),
                    ],
                ])->values()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function questionData(ProductQuestion $question): array
    {
        return [
            'id' => $question->id,
            'question' => $question->question,
            'answer' => $question->answer,
            'askedBy' => $question->asker->name,
            'answeredBy' => $question->answerer?->name,
            'answeredAt' => $question->answered_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{title: string, imageUrl: string, linkUrl: string}>  $fallbacks
     * @return array<int, array<string, mixed>>
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
            'subtitle' => $promotion->subtitle,
            'ctaLabel' => $promotion->cta_label,
            'visualTheme' => $promotion->visual_theme,
            'artworkAlt' => $promotion->artwork_alt,
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

    /** @return array<string, mixed>|null */
    private function flashSaleData(): ?array
    {
        $promotion = $this->promotions->activeFlashSale();

        if ($promotion === null) {
            return null;
        }

        return [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'subtitle' => $promotion->subtitle,
            'endsAt' => $promotion->ends_at?->toIso8601String(),
            'listings' => $promotion->listings->map(fn (Listing $listing): array => $this->listingData($listing))->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function activeCampaignFor(Listing $listing): ?array
    {
        $promotion = $this->promotions->activeFlashSale();

        if ($promotion === null || ! $promotion->listings->contains('id', $listing->id)) {
            return null;
        }

        return [
            'title' => $promotion->title,
            'subtitle' => $promotion->subtitle,
            'endsAt' => $promotion->ends_at?->toIso8601String(),
        ];
    }
}
