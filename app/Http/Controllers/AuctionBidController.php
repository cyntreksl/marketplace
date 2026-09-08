<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidAuctionBidException;
use App\Http\Requests\PlaceBidRequest;
use App\Services\PlaceBidService;
use Illuminate\Http\RedirectResponse;

class AuctionBidController extends Controller
{
    public function store(PlaceBidRequest $request, int $auction, PlaceBidService $bids): RedirectResponse
    {
        try {
            $bids->place($request->user(), $auction, (string) $request->validated('amount'));
        } catch (InvalidAuctionBidException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back();
    }
}
