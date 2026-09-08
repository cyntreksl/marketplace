<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\AdminListingIndexRequest;
use App\Http\Requests\UpdateListingDetailsRequest;
use App\Http\Requests\UpdateListingModerationRequest;
use App\Models\Brand;
use App\Models\Listing;
use App\Services\AdminListingService;
use App\Services\ListingService;
use App\Services\MarketplaceModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminListingController extends Controller
{
    public function index(AdminListingIndexRequest $request, AdminListingService $listings): Response
    {
        $filters = $request->filters('pending_review');

        return Inertia::render('admin/listings/index', [
            'listings' => $listings->moderationQueue($filters),
            'filters' => $filters,
            'view' => 'moderation',
        ]);
    }

    public function products(AdminListingIndexRequest $request, AdminListingService $listings): Response
    {
        $filters = $request->filters('all');

        return Inertia::render('admin/listings/index', [
            'listings' => $listings->allProducts($filters),
            'filters' => $filters,
            'view' => 'all',
        ]);
    }

    public function show(Request $request, Listing $listing, AdminListingService $listings): Response
    {
        abort_unless($request->user()->can('view', $listing), 403);

        return Inertia::render('admin/listings/show', [
            'listing' => $listings->product($listing->id),
        ]);
    }

    public function edit(Request $request, Listing $listing, AdminListingService $listings, CatalogRepository $catalog): Response
    {
        abort_unless($request->user()->can('update', $listing), 403);
        $listing = $listings->product($listing->id);

        return Inertia::render('admin/listings/edit', [
            'listing' => $listing,
            'selectedCategory' => $listing->category === null ? null : $catalog->categoryOption($listing->category),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateDetails(UpdateListingDetailsRequest $request, Listing $listing, ListingService $listings): RedirectResponse
    {
        $listings->updateForModeration($request->user(), $listing, $request->validated());

        return to_route('admin.listings.show', $listing)->with('status', 'Listing details updated.');
    }

    public function update(UpdateListingModerationRequest $request, Listing $listing, MarketplaceModerationService $moderation): RedirectResponse
    {
        abort_unless($request->user()->can('moderate', $listing), 403);
        $moderation->reviewListing($request->user(), $listing, (string) $request->validated('status'), (string) $request->validated('reason'));

        return to_route('admin.listings.show', $listing)->with('status', 'Listing moderation decision saved.');
    }
}
