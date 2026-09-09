<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHomepageCategoriesRequest;
use App\Models\Category;
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
}
