<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerFeedbackIndexRequest;
use App\Services\BuyerPortalService;
use App\Services\MarketplaceSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BuyerFeedbackController extends Controller
{
    public function __construct(
        private readonly BuyerPortalService $portal,
        private readonly MarketplaceSettingsService $settings,
    ) {}

    public function index(BuyerFeedbackIndexRequest $request): Response|RedirectResponse
    {
        if (! $this->settings->productReviewsEnabled()) {
            return redirect()->route('buyer.dashboard');
        }

        return Inertia::render('buyer/feedback/index', $this->portal->feedback($request->user(), $request->view()));
    }
}
