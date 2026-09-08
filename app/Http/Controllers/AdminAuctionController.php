<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelAuctionRequest;
use App\Services\AuctionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuctionController extends Controller
{
    public function __construct(private readonly AuctionService $auctions) {}

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('admin/auctions/index', ['auctions' => $this->auctions->adminIndex()]);
    }

    public function show(Request $request, int $auction): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('admin/auctions/show', ['auction' => $this->auctions->adminAuction($auction)]);
    }

    public function cancel(CancelAuctionRequest $request, int $auction): RedirectResponse
    {
        $this->auctions->cancel($request->user(), $auction, (string) $request->validated('reason'));

        return to_route('admin.auctions.show', $auction)->with('status', 'Auction cancelled and inventory released.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->roles()->whereIn('name', ['admin', 'super_admin'])->exists(), 403);
    }
}
