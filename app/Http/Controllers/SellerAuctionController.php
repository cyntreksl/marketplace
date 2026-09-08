<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuctionRequest;
use App\Services\AuctionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerAuctionController extends Controller
{
    public function __construct(private readonly AuctionService $auctions) {}

    public function index(Request $request): Response
    {
        return Inertia::render('seller/auctions/index', [
            'auctions' => $this->auctions->sellerIndex($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('seller/auctions/create', $this->auctions->sellerCreateData($request->user()));
    }

    public function store(StoreAuctionRequest $request): RedirectResponse
    {
        $auction = $this->auctions->create($request->user(), $request->validated());

        return to_route('seller.auctions.show', $auction)->with('status', 'Auction created.');
    }

    public function show(Request $request, int $auction): Response
    {
        return Inertia::render('seller/auctions/show', [
            'auction' => $this->auctions->sellerAuction($request->user(), $auction),
        ]);
    }

    public function edit(Request $request, int $auction): Response
    {
        return Inertia::render('seller/auctions/edit', [
            'auction' => $this->auctions->sellerAuction($request->user(), $auction),
            ...$this->auctions->sellerCreateData($request->user()),
        ]);
    }

    public function update(StoreAuctionRequest $request, int $auction): RedirectResponse
    {
        $this->auctions->updateDraft($request->user(), $auction, $request->validated());

        return to_route('seller.auctions.show', $auction)->with('status', 'Auction draft updated.');
    }

    public function destroy(Request $request, int $auction): RedirectResponse
    {
        $this->auctions->deleteDraft($request->user(), $auction);

        return to_route('seller.auctions.index')->with('status', 'Auction draft removed.');
    }
}
