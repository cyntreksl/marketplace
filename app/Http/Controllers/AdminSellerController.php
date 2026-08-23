<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSellerStatusRequest;
use App\Models\SellerProfile;
use App\Services\MarketplaceModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSellerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', SellerProfile::class), 403);

        return Inertia::render('admin/sellers/index', [
            'sellers' => SellerProfile::query()->with('user:id,name,email')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function update(UpdateSellerStatusRequest $request, SellerProfile $seller, MarketplaceModerationService $moderation): RedirectResponse
    {
        abort_unless($request->user()->can('review', $seller), 403);
        $moderation->reviewSeller($request->user(), $seller, (string) $request->validated('status'), (string) $request->validated('reason'));

        return to_route('admin.sellers.index')->with('status', 'Seller status updated.');
    }
}
