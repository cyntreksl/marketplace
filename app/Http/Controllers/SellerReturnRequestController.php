<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideReturnRequestRequest;
use App\Http\Requests\SellerReturnIndexRequest;
use App\Models\ReturnRequest;
use App\ReturnStatus;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SellerReturnRequestController extends Controller
{
    public function index(SellerReturnIndexRequest $request, ReturnWorkflowService $returns): Response
    {
        return Inertia::render('seller/returns/index', [
            'returns' => $returns->sellerRequests($request->user(), $request->validated()),
            'filters' => ['q' => $request->validated('q', ''), 'status' => $request->validated('status', 'all')],
        ]);
    }

    public function update(DecideReturnRequestRequest $request, ReturnRequest $returnRequest, ReturnWorkflowService $returns): RedirectResponse
    {
        $decision = ReturnStatus::from($request->validated('decision'));
        $returns->decide($request->user(), $returnRequest->id, $decision, $request->validated('response_reason'));

        return to_route('seller.returns.index')->with('status', "Return request {$decision->value}.");
    }
}
