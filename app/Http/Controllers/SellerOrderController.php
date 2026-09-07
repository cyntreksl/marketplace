<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkSellerOrderShippedRequest;
use App\Http\Requests\SellerOrderIndexRequest;
use App\Models\SellerOrder;
use App\Services\SellerOrderWorkflowService;
use App\Services\SellerPortalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SellerOrderController extends Controller
{
    public function index(SellerOrderIndexRequest $request, SellerPortalService $portal): Response
    {
        return Inertia::render('seller/orders/index', $portal->orders($request->user(), $request->validated()));
    }

    public function show(SellerOrderIndexRequest $request, SellerOrder $sellerOrder, SellerPortalService $portal): Response
    {
        abort_unless($request->user()->can('view', $sellerOrder), 403);

        return Inertia::render('seller/orders/show', ['order' => $portal->order($request->user(), $sellerOrder)]);
    }

    public function processing(SellerOrderIndexRequest $request, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('updateStatus', $sellerOrder), 403);
        $workflow->startProcessing($request->user(), $sellerOrder->id);

        return back()->with('status', 'Order is now being processed.');
    }

    public function ready(SellerOrderIndexRequest $request, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('updateStatus', $sellerOrder), 403);
        $workflow->markReady($request->user(), $sellerOrder->id);

        return back()->with('status', 'Order marked ready to ship.');
    }

    public function shipped(MarkSellerOrderShippedRequest $request, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->ship($request->user(), $sellerOrder->id, $request->validated('courier_name'), $request->validated('tracking_number'));

        return back()->with('status', 'Order dispatched. Tracking is now visible to the buyer.');
    }

    public function delivered(SellerOrderIndexRequest $request, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('updateStatus', $sellerOrder), 403);
        $workflow->deliver($request->user(), $sellerOrder->id);

        return back()->with('status', 'Delivery confirmed. The seven-day return window is now open.');
    }
}
