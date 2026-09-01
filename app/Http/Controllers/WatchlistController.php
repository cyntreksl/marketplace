<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddWatchlistItemRequest;
use App\Models\Listing;
use App\Services\StorefrontService;
use App\Services\WatchlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WatchlistController extends Controller
{
    public function index(Request $request, WatchlistService $watchlists, StorefrontService $storefront): Response
    {
        return Inertia::render('storefront/watchlist/index', [
            'categories' => $storefront->navigationCategories(),
            'listings' => $watchlists->listingsFor($request->user())->map(fn (Listing $listing): array => $storefront->cardData($listing))->values(),
        ]);
    }

    public function store(AddWatchlistItemRequest $request, string $listing, WatchlistService $watchlists): RedirectResponse
    {
        $watchlists->add($request->user(), $listing);

        return back()->with('toast', ['type' => 'success', 'message' => 'Added to your wishlist.']);
    }

    public function destroy(Request $request, Listing $listing, WatchlistService $watchlists): RedirectResponse
    {
        $watchlists->remove($request->user(), $listing);

        return back()->with('toast', ['type' => 'success', 'message' => 'Removed from your wishlist.']);
    }
}
