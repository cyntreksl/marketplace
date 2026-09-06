<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerFeedbackIndexRequest;
use App\Services\BuyerPortalService;
use Inertia\Inertia;
use Inertia\Response;

class BuyerFeedbackController extends Controller
{
    public function __construct(private readonly BuyerPortalService $portal) {}

    public function index(BuyerFeedbackIndexRequest $request): Response
    {
        return Inertia::render('buyer/feedback/index', $this->portal->feedback($request->user(), $request->view()));
    }
}
