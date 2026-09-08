<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\ProductQuestionRepository;
use App\Contracts\Repositories\PromotionRepository;
use App\Contracts\Repositories\ReviewRepository;
use App\Contracts\Repositories\SellerStoreRepository;
use App\Contracts\Repositories\WatchlistRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Models\User;
use App\Models\WholesalePriceTier;
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
        private readonly SellerStoreRepository $sellers,
        private readonly SellerSummaryService $sellerSummaries,
    ) {}

    /** @return array<string, mixed> */
    public function homeData(): array
    {
        return [
            'categories' => $this->storefrontCategories(),
            'promotions' => [
                'hero' => [
                    [
                        'id' => null,
                        'title' => 'Bring home better deals',
                        'artworkAlt' => 'ProDeals.lk banner promoting up to 40 percent off selected appliances, gadgets, and essentials',
                        'containsEmbeddedCopy' => true,
                        'imageUrl' => $this->staticMedia->url('images/storefront/home-deals-banner.png', versioned: true),
                        'linkUrl' => '/collections/deals',
                    ],
                ],
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
    public function browseData(array $filters, string $channel = 'retail'): array
    {
        $selectedCategorySlug = $filters['category'] ?? null;

        return [
            'filters' => $filters,
            'listings' => $this->listings->paginatePublic($filters, $channel)->through(fn (Listing $listing) => $this->listingData($listing, channel: $channel)),
            'pageHeading' => 'Online Shopping in Sri Lanka',
            'intro' => 'Browse products from approved Sri Lankan sellers, compare prices, and buy securely through ProDeals.lk.',
            'categories' => $this->storefrontCategories($channel),
            'categoryContext' => is_string($selectedCategorySlug)
                ? $this->catalog->activeCategoryContextBySlug($selectedCategorySlug, $channel)
                : null,
            'filterOptions' => [
                'brands' => $this->catalog->availableBrands($channel)
                    ->map(fn (Brand $brand): array => $brand->only(['id', 'name', 'slug']))
                    ->values(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listingIndexData(array $filters): array
    {
        $data = $this->browseData($filters);
        $page = max(1, (int) request()->query('page', 1));
        $hasFilters = collect(request()->query())->except('page')->filter()->isNotEmpty();
        $canonical = route('listings.index').(! $hasFilters && $page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: 'Online Shopping in Sri Lanka - '.config('app.name'),
            description: 'Shop products from approved Sri Lankan sellers with clear prices and secure checkout on '.config('app.name').'.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Shop', 'url' => route('listings.index')],
            ],
            indexable: ! $hasFilters && $this->listings->retailProductCount() > 0,
            items: $this->catalogItems($data),
        );

        return [
            ...$data,
            'browseUrl' => route('listings.index'),
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function wholesaleData(array $filters): array
    {
        $data = $this->browseData($filters, 'wholesale');
        $page = max(1, (int) request()->query('page', 1));
        $hasFilters = collect(request()->query())->except('page')->filter()->isNotEmpty();
        $canonical = route('wholesale.index').(! $hasFilters && $page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: 'Wholesale Products in Sri Lanka - '.config('app.name'),
            description: 'Buy wholesale products in bulk from approved Sri Lankan sellers with clear minimum quantities and wholesale pricing.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Wholesale', 'url' => route('wholesale.index')],
            ],
            indexable: ! $hasFilters && $this->listings->wholesaleProductCount() > 0,
            items: $this->catalogItems($data),
        );

        return [
            ...$data,
            'browseUrl' => route('wholesale.index'),
            'pageHeading' => 'Wholesale Products',
            'intro' => 'Buy in bulk from approved sellers with transparent minimum order quantities and wholesale unit prices.',
            'catalogMode' => 'wholesale',
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
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
        $title = filled($category->seo_title) ? (string) $category->seo_title : $category->name.' in Sri Lanka - '.config('app.name');
        $description = filled($category->seo_description)
            ? (string) $category->seo_description
            : 'Shop '.$category->name.' from trusted Sri Lankan sellers on '.config('app.name').'.';
        $ancestorBreadcrumbs = [];

        foreach ((array) ($data['categoryContext']['ancestors'] ?? []) as $ancestor) {
            if (! is_array($ancestor) || ! is_string($ancestor['name'] ?? null) || ! is_string($ancestor['slug'] ?? null)) {
                continue;
            }

            $ancestorBreadcrumbs[] = [
                'name' => $ancestor['name'],
                'url' => route('categories.show', $ancestor['slug']),
            ];
        }

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ...$ancestorBreadcrumbs,
            ['name' => $category->name, 'url' => route('categories.show', $slug)],
        ];
        $indexable = ! $hasFilters && $this->catalog->categoryHasVisibleProducts($category);
        $seo = $this->seo->catalogPayload(
            title: $title,
            description: $description,
            canonical: $canonical,
            breadcrumbs: $breadcrumbs,
            indexable: $indexable,
            items: $this->catalogItems($data),
        );

        return [
            ...$data,
            'browseUrl' => route('categories.show', $slug),
            'pageHeading' => $category->name,
            'intro' => filled($category->seo_intro) ? $category->seo_intro : $description,
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
        ];
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
        $title = filled($brand->seo_title) ? (string) $brand->seo_title : $brand->name.' Products in Sri Lanka - '.config('app.name');
        $description = filled($brand->seo_description)
            ? (string) $brand->seo_description
            : 'Shop '.$brand->name.' products from trusted Sri Lankan sellers on '.config('app.name').'.';
        $seo = $this->seo->catalogPayload(
            title: $title,
            description: $description,
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Brands', 'url' => route('brands.index')],
                ['name' => $brand->name, 'url' => route('brands.show', $slug)],
            ],
            indexable: ! $hasFilters && $this->catalog->brandHasVisibleProducts($brand),
            items: $this->catalogItems($data),
        );

        return [
            ...$data,
            'browseUrl' => route('brands.show', $slug),
            'pageHeading' => $brand->name,
            'intro' => filled($brand->seo_intro) ? $brand->seo_intro : $description,
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function collectionData(string $collection, array $filters): array
    {
        $labels = [
            'featured' => 'Featured Products',
            'deals' => 'Latest Deals',
            'best-sellers' => 'Best Sellers',
            'new-arrivals' => 'New Arrivals',
            'clearance' => 'Clearance Deals',
        ];
        $label = $labels[$collection] ?? 'Products';
        $data = $this->browseData([...$filters, 'collection' => $collection]);
        $page = max(1, (int) request()->query('page', 1));
        $canonical = route('collections.show', $collection).($page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: $label.' in Sri Lanka - '.config('app.name'),
            description: 'Discover '.$label.' from approved sellers across Sri Lanka on '.config('app.name').'.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $label, 'url' => route('collections.show', $collection)],
            ],
            indexable: collect(request()->query())->except('page')->filter()->isEmpty()
                && in_array($collection, $this->listings->indexableCollectionSlugs(), true),
            items: $this->catalogItems($data),
        );

        return [
            ...$data,
            'browseUrl' => route('collections.show', $collection),
            'pageHeading' => $label,
            'intro' => 'Fresh marketplace picks selected from approved ProDeals.lk sellers.',
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function auctionData(array $filters): array
    {
        $data = $this->browseData([...$filters, 'listing_type' => 'auction']);
        $page = max(1, (int) request()->query('page', 1));
        $hasFilters = collect(request()->query())->except('page')->filter()->isNotEmpty();
        $canonical = route('auctions.index').(! $hasFilters && $page > 1 ? '?page='.$page : '');
        $seo = $this->seo->catalogPayload(
            title: 'Online Auctions in Sri Lanka - '.config('app.name'),
            description: 'Bid on active and ending-soon auctions from approved Sri Lankan sellers on '.config('app.name').'.',
            canonical: $canonical,
            breadcrumbs: [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Auctions', 'url' => route('auctions.index')],
            ],
            indexable: ! $hasFilters && $this->listings->indexableAuctionCount() > 0,
            items: $this->catalogItems($data),
        );

        return [
            ...$data,
            'browseUrl' => route('auctions.index'),
            'pageHeading' => 'Online Auctions in Sri Lanka',
            'intro' => 'Discover live auctions, see current prices, and bid before the closing time.',
            'seo' => $seo,
            'head' => $this->seo->tags($seo),
        ];
    }

    /** @return array<string, mixed> */
    public function listingDetailsData(string $slug, ?User $viewer = null, ?int $requestedVariantId = null, bool $wholesaleIntent = false): array
    {
        $listing = $this->listings->findPublicBySlug($slug);
        $channel = ($wholesaleIntent || ! $listing->is_retail_enabled) && $listing->is_wholesale_enabled
            ? 'wholesale'
            : 'retail';
        $categoryTrail = $listing->category === null
            ? []
            : $this->catalog->activeCategoryTrailBySlug($listing->category->slug, $channel);
        $seo = $this->seo->listingPayload($listing, $categoryTrail);
        $selectedVariantId = $listing->variants
            ->where('is_active', true)
            ->firstWhere('id', $requestedVariantId)?->id;
        if ($selectedVariantId === null && $channel === 'wholesale' && $listing->product_type === 'variant') {
            $selectedVariantId = $listing->variants->where('is_active', true)->first()?->id;
        }
        $selectedVariant = $listing->variants->firstWhere('id', $selectedVariantId);
        $initialWholesaleQuantity = $selectedVariant === null
            ? $listing->wholesalePriceTiers->min('minimum_quantity')
            : $selectedVariant->wholesalePriceTiers->min('minimum_quantity');

        return [
            'head' => $this->seo->tags($seo),
            'seo' => $seo,
            'listing' => $this->listingData($listing, detailed: true, channel: $channel),
            'selectedVariantId' => $selectedVariantId,
            'purchaseContext' => [
                'channel' => $channel,
                'initialQuantity' => $channel === 'wholesale'
                    ? max(2, (int) ($initialWholesaleQuantity ?? 2))
                    : 1,
            ],
            'sellerSummary' => $listing->sellerProfile === null ? null : $this->sellerSummaries->forSeller($this->sellers->findPublic($listing->sellerProfile->slug)),
            'reviews' => $this->reviews->forListing((int) $listing->id, 20)->map(fn ($review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'buyerName' => $review->buyer->name,
                'createdAt' => $review->created_at->toDateString(),
            ])->values(),
            'categories' => $this->storefrontCategories($channel),
            'categoryTrail' => $categoryTrail,
            'questions' => $this->questions->answeredFor($listing)->map(fn ($question): array => $this->questionData($question))->values(),
            'pendingQuestions' => $this->questions->pendingForViewer($listing, $viewer)->map(fn ($question): array => $this->questionData($question))->values(),
            'isWishlisted' => $viewer === null ? false : $this->watchlists->contains($viewer, $listing),
            'activeCampaign' => $this->activeCampaignFor($listing),
            'categoryPolicies' => $listing->category === null ? null : [
                'returnWindowDays' => $listing->category->return_window_days,
                'codEnabled' => $listing->category->cod_enabled,
            ],
            'relatedListings' => $this->listings->related($listing, $channel)->map(fn (Listing $related): array => $this->listingData($related, channel: $channel))->values(),
            'sellerListings' => $this->listings->otherListingsFromSeller($listing, $channel)->map(fn (Listing $sellerListing): array => $this->listingData($sellerListing, channel: $channel))->values(),
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
    private function storefrontCategories(string $channel = 'retail'): Collection
    {
        return $this->catalog->activeTopLevelCategories($channel)
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
    private function listingData(Listing $listing, bool $detailed = false, string $channel = 'retail'): array
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
            'effectivePrice' => $channel === 'wholesale'
                ? $listing->wholesale_price
                : ($listing->auction === null ? $listing->buyNowPrice() : $listing->auction->current_price),
            'retailEnabled' => $listing->is_retail_enabled,
            'wholesaleEnabled' => $listing->is_wholesale_enabled,
            'wholesalePrice' => $listing->wholesale_price,
            'wholesaleMinimumQuantity' => $listing->wholesale_min_quantity,
            'wholesaleTiers' => $detailed
                ? $this->wholesaleTierData($listing->wholesalePriceTiers)
                : [],
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
                    'wholesalePrice' => $variant->wholesale_price,
                    'wholesaleMinimumQuantity' => $variant->wholesale_min_quantity,
                    'wholesaleTiers' => $this->wholesaleTierData($variant->wholesalePriceTiers),
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

    /** @param Collection<int, WholesalePriceTier> $tiers
     * @return array<int, array{minimumQuantity: int, unitPrice: string}>
     */
    private function wholesaleTierData(Collection $tiers): array
    {
        return $tiers
            ->sortBy('minimum_quantity')
            ->map(fn ($tier): array => [
                'minimumQuantity' => (int) $tier->minimum_quantity,
                'unitPrice' => (string) $tier->unit_price,
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function catalogItems(array $data): array
    {
        return array_values($data['listings']->items());
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
