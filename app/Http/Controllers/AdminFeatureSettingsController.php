<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFeatureSettingsRequest;
use App\Services\MarketplaceFeatureService;
use App\Services\MarketplaceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFeatureSettingsController extends Controller
{
    public function index(Request $request, MarketplaceSettingsService $settings): Response
    {
        abort_unless($request->user()?->roles()->whereIn('name', ['admin', 'super_admin'])->exists(), 403);

        return Inertia::render('admin/features/index', ['flags' => $settings->reviewFlags()]);
    }

    public function update(UpdateFeatureSettingsRequest $request, MarketplaceFeatureService $features): RedirectResponse
    {
        $features->updateReviewFlags($request->user(), $request->validated());

        return back()->with('status', 'Feature settings updated.');
    }
}
