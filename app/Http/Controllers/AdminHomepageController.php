<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHomepageCategoriesRequest;
use App\Http\Requests\UpdateListingMerchandisingRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Services\HomeMerchandisingService;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminHomepageController extends Controller
{
    public function index(Request $request, HomeMerchandisingService $merchandising, PromotionService $promotions): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('admin/homepage/index', [
            ...$merchandising->adminData($request->only(['search', 'status'])),
            'promotions' => $promotions->adminPromotions(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function updateCategories(UpdateHomepageCategoriesRequest $request, HomeMerchandisingService $merchandising): RedirectResponse
    {
        $data = $request->validated();
        $merchandising->updateCategories(
            $request->user(),
            $data['popular_category_ids'],
            $data['featured_category_ids'],
            $data['reason'],
        );

        return to_route('admin.homepage.index')->with('status', 'Homepage categories updated.');
    }

    public function updateListing(UpdateListingMerchandisingRequest $request, Listing $listing, HomeMerchandisingService $merchandising): RedirectResponse
    {
        $merchandising->updateListing(
            $request->user(),
            $listing,
            [
                'is_featured' => $request->boolean('is_featured'),
                'is_best_offer' => $request->boolean('is_best_offer'),
                'is_best_seller' => $request->boolean('is_best_seller'),
                'is_new_arrival' => $request->boolean('is_new_arrival'),
                'is_clearance' => $request->boolean('is_clearance'),
            ],
            $request->validated('reason'),
        );

        return back()->with('status', 'Listing homepage merchandising updated.');
    }
}
