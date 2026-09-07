<?php

namespace App\Http\Controllers;

use App\Services\SellerPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerDashboardController extends Controller
{
    public function __invoke(Request $request, SellerPortalService $portal): Response
    {
        return Inertia::render('seller/overview', [
            ...$portal->dashboard($request->user()),
            'activity' => Inertia::defer(fn (): array => $portal->activity($request->user())),
        ]);
    }
}
