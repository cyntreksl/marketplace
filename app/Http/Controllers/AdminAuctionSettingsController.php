<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAuctionSettingsRequest;
use App\Services\AuctionService;
use App\Services\MarketplaceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuctionSettingsController extends Controller
{
    public function index(Request $request, MarketplaceSettingsService $settings): Response
    {
        abort_unless($request->user()?->roles()->whereIn('name', ['admin', 'super_admin'])->exists(), 403);

        return Inertia::render('admin/auctions/settings', ['flags' => $settings->auctionFlags()]);
    }

    public function update(UpdateAuctionSettingsRequest $request, AuctionService $auctions): RedirectResponse
    {
        $validated = $request->validated();
        $auctions->updateFlags($request->user(), [
            'auction.enabled' => (bool) $validated['enabled'],
            'auction.types.normal.enabled' => (bool) $validated['types']['normal'],
            'auction.types.blind.enabled' => (bool) $validated['types']['blind'],
            'auction.types.time_extended.enabled' => (bool) $validated['types']['time_extended'],
        ]);

        return back()->with('status', 'Auction feature flags updated.');
    }
}
