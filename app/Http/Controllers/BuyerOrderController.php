<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyerOrderIndexRequest;
use App\Models\CustomerOrder;
use App\Services\BuyerPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BuyerOrderController extends Controller
{
    public function __construct(private readonly BuyerPortalService $portal) {}

    public function index(BuyerOrderIndexRequest $request): Response
    {
        return Inertia::render('buyer/orders/index', $this->portal->orders($request->user(), $request->stage()));
    }

    public function show(Request $request, CustomerOrder $customerOrder): Response
    {
        Gate::authorize('view', $customerOrder);

        return Inertia::render('buyer/orders/show', [
            'order' => $this->portal->order($request->user(), $customerOrder),
        ]);
    }
}
