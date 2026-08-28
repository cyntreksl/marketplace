<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideReturnRequestRequest;
use App\Models\ReturnRequest;
use App\ReturnStatus;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerReturnRequestController extends Controller
{
    public function index(Request $request, ReturnWorkflowService $returns): Response
    {
        return Inertia::render('seller/returns/index', [
            'returns' => $returns->sellerRequests($request->user()),
        ]);
    }

    public function update(DecideReturnRequestRequest $request, ReturnRequest $returnRequest, ReturnWorkflowService $returns): RedirectResponse
    {
        $decision = ReturnStatus::from($request->validated('decision'));
        $returns->decide($request->user(), $returnRequest->id, $decision, $request->validated('response_reason'));

        return to_route('seller.returns.index')->with('status', "Return request {$decision->value}.");
    }
}
