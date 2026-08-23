<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerOnboardingRequest;
use App\Models\SellerProfile;
use App\Services\SellerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerOnboardingController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('seller/onboarding', ['seller' => SellerProfile::query()->where('user_id', $request->user()->id)->first()]);
    }

    public function update(StoreSellerOnboardingRequest $request, SellerOnboardingService $onboarding): RedirectResponse
    {
        $onboarding->store($request->user(), $request->safe()->except('accept_terms'));

        return to_route('seller.onboarding.edit');
    }
}
