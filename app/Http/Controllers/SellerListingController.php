<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\SubmitListingRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Services\ListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerListingController extends Controller
{
    public function index(Request $request): Response
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();

        return Inertia::render('seller/listings/index', [
            'sellerStatus' => $seller->status,
            'listings' => $seller->listings()->with(['category:id,name', 'auction:id,listing_id,status,starts_at,ends_at'])->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('seller/listings/create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'commission_percentage']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreListingRequest $request, ListingService $listings): RedirectResponse
    {
        $listing = $listings->createDraft($request->user(), $request->validated());

        return to_route('seller.listings.index')->with('status', "{$listing->title} was saved as a draft.");
    }

    public function submit(SubmitListingRequest $request, ListingService $listings): RedirectResponse
    {
        $listing = $listings->submit($request->user(), (int) $request->validated('listing_id'));

        return to_route('seller.listings.index')->with('status', "{$listing->title} was submitted for review.");
    }
}
