<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturnRequestRequest;
use App\ReturnReason;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerReturnRequestController extends Controller
{
    public function index(Request $request, ReturnWorkflowService $returns): Response
    {
        return Inertia::render('buyer/returns/index', [
            'items' => $returns->buyerItems($request->user()),
            'returns' => $returns->buyerRequests($request->user()),
            'reasons' => collect(ReturnReason::cases())->map(fn (ReturnReason $reason): array => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ]),
        ]);
    }

    public function store(StoreReturnRequestRequest $request, ReturnWorkflowService $returns): RedirectResponse
    {
        $validated = $request->validated();
        $returns->requestReturn($request->user(), [
            'order_item_id' => $validated['order_item_id'],
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'evidence' => $validated['evidence'] ?? [],
        ]);

        return to_route('buyer.returns.index')->with('status', 'Return request submitted for the seller to review.');
    }
}
