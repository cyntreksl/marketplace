<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayoutRequest;
use App\Services\SellerPortalService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerWalletController extends Controller
{
    public function index(Request $request, SellerPortalService $portal): Response
    {
        return Inertia::render('seller/wallet', $portal->wallet($request->user()));
    }

    public function store(StorePayoutRequest $request, SettlementService $settlements): RedirectResponse
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $settlements->requestPayout($request->user(), $seller, (string) $request->validated('amount'));

        return to_route('seller.wallet.index')->with('status', 'Payout request submitted.');
    }
}
