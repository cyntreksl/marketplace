<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateListingModerationRequest;
use App\Models\Listing;
use App\Services\MarketplaceModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminListingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Listing::class), 403);

        return Inertia::render('admin/listings/index', [
            'listings' => Listing::query()->with(['sellerProfile:id,store_name', 'category:id,name'])->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function update(UpdateListingModerationRequest $request, Listing $listing, MarketplaceModerationService $moderation): RedirectResponse
    {
        abort_unless($request->user()->can('moderate', $listing), 403);
        $moderation->reviewListing($request->user(), $listing, (string) $request->validated('status'), (string) $request->validated('reason'));

        return to_route('admin.listings.index')->with('status', 'Listing moderation decision saved.');
    }
}
