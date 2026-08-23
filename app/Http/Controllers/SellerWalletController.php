<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayoutRequest;
use App\Services\SellerLedgerService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerWalletController extends Controller
{
    public function index(Request $request, SellerLedgerService $ledger): Response
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();

        return Inertia::render('seller/wallet', [
            'availableBalance' => $ledger->availableBalance($seller),
            'entries' => $seller->ledgerEntries()->latest()->paginate(20)->withQueryString(),
            'payouts' => $seller->payoutRequests()->latest()->get(),
        ]);
    }

    public function store(StorePayoutRequest $request, SettlementService $settlements): RedirectResponse
    {
        $seller = $request->user()->sellerProfile()->firstOrFail();
        $settlements->requestPayout($request->user(), $seller, (string) $request->validated('amount'));

        return to_route('seller.wallet.index')->with('status', 'Payout request submitted.');
    }
}
