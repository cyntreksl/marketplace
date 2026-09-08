<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminSearchInsightsRequest;
use App\Services\SearchAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AdminSearchInsightsController extends Controller
{
    public function __invoke(AdminSearchInsightsRequest $request, SearchAnalyticsService $analytics): Response
    {
        return Inertia::render('admin/search-insights/index', $analytics->report($request->days()));
    }
}
