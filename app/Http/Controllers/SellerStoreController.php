<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorefrontBrowseRequest;
use App\Http\Requests\UpdateSellerStoreRequest;
use App\Services\SellerBrandingService;
use App\Services\SellerStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerStoreController extends Controller
{
    public function __construct(private readonly SellerStoreService $stores, private readonly SellerBrandingService $branding) {}

    public function show(StorefrontBrowseRequest $request, string $seller): Response
    {
        return Inertia::render('storefront/stores/show', $this->stores->publicData($seller, $request->filters(), max(1, $request->integer('page', 1))));
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('seller/store', $this->stores->settingsData((int) $request->user()->id));
    }

    public function update(UpdateSellerStoreRequest $request): RedirectResponse
    {
        $seller = $this->stores->ownedSeller((int) $request->user()->id);
        $this->branding->update($seller, $request->validated());

        return to_route('seller.store.edit')->with('success', 'Your store branding is now live.');
    }
}
