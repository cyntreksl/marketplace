<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecentlyViewedListingsRequest;
use App\Http\Requests\StorefrontBrowseRequest;
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

        return Inertia::render('storefront/listings/index', $this->storefront->browseData($filters));
    }

    public function category(StorefrontBrowseRequest $request, string $category): Response
    {
        return Inertia::render('storefront/listings/index', $this->storefront->categoryData($category, $request->filters()));
    }

    public function brand(StorefrontBrowseRequest $request, string $brand): Response
    {
        return Inertia::render('storefront/listings/index', $this->storefront->brandData($brand, $request->filters()));
    }

    public function collection(StorefrontBrowseRequest $request, string $collection): Response
    {
        return Inertia::render('storefront/listings/index', $this->storefront->browseData([
            ...$request->filters(),
            'collection' => $collection,
        ]));
    }

    public function show(Request $request, string $listing): Response
    {
        $variantId = filter_var($request->query('variant'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return Inertia::render('storefront/listings/show', $this->storefront->listingDetailsData(
            $listing,
            $request->user(),
            $variantId === false ? null : $variantId,
        ));
    }

    public function recent(RecentlyViewedListingsRequest $request): JsonResponse
    {
        return response()->json([
            'listings' => $this->storefront->recentlyViewedData($request->validated('ids')),
        ]);
    }
}
