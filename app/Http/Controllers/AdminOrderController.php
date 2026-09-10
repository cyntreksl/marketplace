<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminOrderIndexRequest;
use App\Http\Requests\AdminSellerOrderActionRequest;
use App\Http\Requests\CancelSellerOrderRequest;
use App\Http\Requests\CompleteCancellationRefundRequest;
use App\Models\CustomerOrder;
use App\Models\SellerOrder;
use App\Services\AdminOrderService;
use App\Services\SellerOrderCancellationService;
use App\Services\SellerOrderWorkflowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    public function index(AdminOrderIndexRequest $request, AdminOrderService $orders): Response
    {
        return Inertia::render('admin/orders/index', $orders->index($request->filters()));
    }

    public function show(AdminOrderIndexRequest $request, CustomerOrder $customerOrder, AdminOrderService $orders): Response
    {
        return Inertia::render('admin/orders/show', ['order' => $orders->show($customerOrder)]);
    }

    public function processing(AdminSellerOrderActionRequest $request, CustomerOrder $customerOrder, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->startProcessing($request->user(), $sellerOrder->id);

        return back()->with('status', 'The seller package is now being processed.');
    }

    public function ready(AdminSellerOrderActionRequest $request, CustomerOrder $customerOrder, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->markReady($request->user(), $sellerOrder->id);

        return back()->with('status', 'The seller package is ready to ship.');
    }

    public function shipped(AdminSellerOrderActionRequest $request, CustomerOrder $customerOrder, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->ship($request->user(), $sellerOrder->id, $request->validated('courier_name'), $request->validated('tracking_number'));

        return back()->with('status', 'The seller package was dispatched.');
    }

    public function delivered(AdminSellerOrderActionRequest $request, CustomerOrder $customerOrder, SellerOrder $sellerOrder, SellerOrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->deliver($request->user(), $sellerOrder->id);

        return back()->with('status', 'Delivery was confirmed on behalf of the seller.');
    }

    public function cancel(CancelSellerOrderRequest $request, CustomerOrder $customerOrder, SellerOrder $sellerOrder, SellerOrderCancellationService $cancellations): RedirectResponse
    {
        $cancellations->cancel($request->user(), $sellerOrder->id, (string) $request->validated('reason'));

        return back()->with('status', 'The seller package was cancelled.');
    }

    public function refund(CompleteCancellationRefundRequest $request, CustomerOrder $customerOrder, SellerOrder $sellerOrder, SellerOrderCancellationService $cancellations): RedirectResponse
    {
        $cancellations->completeRefund(
            $request->user(),
            $sellerOrder->id,
            (string) $request->validated('amount'),
            (string) $request->validated('reference'),
        );

        return back()->with('status', 'The cancellation refund was recorded.');
    }
}
