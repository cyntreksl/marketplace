<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerPaymentIndexRequest;
use App\Services\BuyerPortalService;
use Inertia\Inertia;
use Inertia\Response;

class BuyerPaymentController extends Controller
{
    public function __construct(private readonly BuyerPortalService $portal) {}

    public function index(BuyerPaymentIndexRequest $request): Response
    {
        return Inertia::render('buyer/payments/index', $this->portal->payments($request->user(), $request->status()));
    }
}
