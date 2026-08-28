<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\SubmitListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Models\Brand;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerListingController extends Controller
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly ListingService $listings,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('seller/listings/index', $this->listings->sellerIndex($request->user()));
    }

    public function create(Request $request): Response
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();

        return Inertia::render('seller/listings/create', [
            'sellerStatus' => $seller->status,
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Listing $listing): Response
    {
        abort_unless($request->user()->can('update', $listing), 403);
        $seller = $request->user()->sellerProfile()->firstOrFail();

        return Inertia::render('seller/listings/edit', [
            'listing' => $listing->load(['auction', 'media', 'category']),
            'sellerStatus' => $seller->status,
            'selectedCategory' => $this->catalog->categoryOption($listing->category),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreListingRequest $request): RedirectResponse
    {
        $listing = $this->listings->createDraft($request->user(), $request->validated());

        return to_route('seller.listings.index')->with('status', $listing->status === 'pending_review'
            ? "{$listing->title} was submitted for review."
            : "{$listing->title} was saved as a draft.");
    }

    public function update(UpdateListingRequest $request, Listing $listing): RedirectResponse
    {
        $listing = $this->listings->updateDraft($request->user(), $listing, $request->validated());

        return to_route('seller.listings.index')->with('status', $listing->status === 'pending_review'
            ? "{$listing->title} was submitted for review."
            : "{$listing->title} was updated as a draft.");
    }

    public function submit(SubmitListingRequest $request): RedirectResponse
    {
        $listing = $this->listings->submit($request->user(), (int) $request->validated('listing_id'));

        return to_route('seller.listings.index')->with('status', "{$listing->title} was submitted for review.");
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $listing), 403);
        $outcome = $this->listings->removeOrArchive($request->user(), $listing->id);

        return to_route('seller.listings.index')->with('status', $outcome === 'archived'
            ? "{$listing->title} was archived because it has orders."
            : "{$listing->title} was removed.");
    }
}
