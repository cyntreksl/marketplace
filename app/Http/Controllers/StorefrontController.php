<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecentlyViewedListingsRequest;
use App\Http\Requests\StorefrontBrowseRequest;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
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

    public function index(StorefrontBrowseRequest $request): Response
    {
        return Inertia::render('storefront/listings/index', $this->storefront->browseData($request->filters()));
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
        return Inertia::render('storefront/listings/show', $this->storefront->listingDetailsData($listing, $request->user()));
    }

    public function recent(RecentlyViewedListingsRequest $request): JsonResponse
    {
        return response()->json([
            'listings' => $this->storefront->recentlyViewedData($request->validated('ids')),
        ]);
    }
}
