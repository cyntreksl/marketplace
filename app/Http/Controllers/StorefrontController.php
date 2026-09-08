<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecentlyViewedListingsRequest;
use App\Http\Requests\StorefrontBrowseRequest;
use App\Services\MetaConversionsService;
use App\Services\SearchAnalyticsService;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly StorefrontService $storefront,
        private readonly MetaConversionsService $metaConversions,
        private readonly SearchAnalyticsService $searchAnalytics,
    ) {}

    public function home(): Response
    {
        return Inertia::render('storefront/home', $this->storefront->homeData());
    }

    public function index(StorefrontBrowseRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();
        $meaningfulFilters = collect($filters)->except('sort')->filter(fn (mixed $value): bool => filled($value));
        $hasExplicitSort = $request->filled('sort');

        if ($meaningfulFilters->count() === 1 && ! $hasExplicitSort) {
            $page = max(1, (int) $request->query('page', 1));
            $parameters = $page > 1 ? ['page' => $page] : [];

            if (filled($filters['category'] ?? null)) {
                return redirect()->route('categories.show', ['category' => $filters['category'], ...$parameters], 301);
            }

            if (filled($filters['brand'] ?? null)) {
                return redirect()->route('brands.show', ['brand' => $filters['brand'], ...$parameters], 301);
            }
        }

        $data = $this->storefront->listingIndexData($filters);
        $this->searchAnalytics->track($request, $filters, 'listings', $data['listings']->total());

        return Inertia::render('storefront/listings/index', $data);
    }

    public function category(StorefrontBrowseRequest $request, string $category): Response
    {
        $filters = $request->filters();
        $data = $this->storefront->categoryData($category, $filters);
        $this->searchAnalytics->track($request, [...$filters, 'category' => $category], 'category', $data['listings']->total());

        return Inertia::render('storefront/listings/index', $data);
    }

    public function brand(StorefrontBrowseRequest $request, string $brand): Response
    {
        $filters = $request->filters();
        $data = $this->storefront->brandData($brand, $filters);
        $this->searchAnalytics->track($request, [...$filters, 'brand' => $brand], 'brand', $data['listings']->total());

        return Inertia::render('storefront/listings/index', $data);
    }

    public function collection(StorefrontBrowseRequest $request, string $collection): Response
    {
        $filters = $request->filters();
        $data = $this->storefront->collectionData($collection, $filters);
        $this->searchAnalytics->track($request, [...$filters, 'collection' => $collection], 'collection', $data['listings']->total());

        return Inertia::render('storefront/listings/index', $data);
    }

    public function auctions(StorefrontBrowseRequest $request): Response
    {
        $filters = $request->filters();
        $data = $this->storefront->auctionData($filters);
        $this->searchAnalytics->track($request, [...$filters, 'listing_type' => 'auction'], 'auctions', $data['listings']->total());

        return Inertia::render('storefront/listings/index', $data);
    }

    public function show(Request $request, string $listing): Response
    {
        $variantId = filter_var($request->query('variant'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $details = $this->storefront->listingDetailsData(
            $listing,
            $request->user(),
            $variantId === false ? null : $variantId,
        );
        $this->metaConversions->trackViewContent($request, $details['listing'], $details['selectedVariantId']);

        return Inertia::render('storefront/listings/show', $details);
    }

    public function recent(RecentlyViewedListingsRequest $request): JsonResponse
    {
        return response()->json([
            'listings' => $this->storefront->recentlyViewedData($request->validated('ids')),
        ]);
    }
}
