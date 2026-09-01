<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackOrderRequest;
use App\Services\OrderTrackingService;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function index(StorefrontService $storefront): Response
    {
        return Inertia::render('storefront/order-tracking', ['categories' => $storefront->navigationCategories()]);
    }

    public function store(TrackOrderRequest $request, OrderTrackingService $tracking): JsonResponse
    {
        return response()->json([
            'order' => $tracking->lookup($request->validated('number'), $request->validated('email')),
        ]);
    }
}
