<?php

namespace App\Http\Controllers;

use App\Services\BuyerPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerDashboardController extends Controller
{
    public function __construct(private readonly BuyerPortalService $portal) {}

    public function index(Request $request): Response
    {
        $buyer = $request->user();

        return Inertia::render('buyer/overview', [
            'summary' => $this->portal->overview($buyer),
            'activity' => Inertia::defer(fn (): array => $this->portal->activity($buyer)),
        ]);
    }
}
