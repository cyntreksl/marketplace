<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompareListingsRequest;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComparisonController extends Controller
{
    public function index(StorefrontService $storefront): Response
    {
        return Inertia::render('storefront/compare', ['categories' => $storefront->navigationCategories()]);
    }

    public function listings(CompareListingsRequest $request, StorefrontService $storefront): JsonResponse
    {
        return response()->json(['listings' => $storefront->comparisonData($request->validated('ids'))]);
    }
}
