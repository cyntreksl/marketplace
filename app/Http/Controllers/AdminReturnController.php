<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteManualRefundRequest;
use App\Http\Requests\MarkReturnRefundReadyRequest;
use App\Http\Requests\ProcessCardRefundRequest;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminReturnController extends Controller
{
    public function index(Request $request, RefundService $refunds): Response
    {
        Gate::authorize('viewAny', Refund::class);

        return Inertia::render('admin/returns/index', [
            'returns' => $refunds->operationsQueue(),
        ]);
    }

    public function ready(MarkReturnRefundReadyRequest $request, ReturnRequest $returnRequest, RefundService $refunds): RedirectResponse
    {
        $refunds->markReady($request->user(), $returnRequest->id);

        return to_route('admin.returns.index')->with('status', 'Return marked ready for refund.');
    }

    public function refund(ProcessCardRefundRequest $request, ReturnRequest $returnRequest, RefundService $refunds): RedirectResponse
    {
        $refund = $refunds->processCard($request->user(), $returnRequest->id);

        return to_route('admin.returns.index')->with('status', "Card refund is {$refund->status->value}.");
    }

    public function manual(CompleteManualRefundRequest $request, ReturnRequest $returnRequest, RefundService $refunds): RedirectResponse
    {
        $refunds->completeManual($request->user(), $returnRequest->id, $request->validated('reference'));

        return to_route('admin.returns.index')->with('status', 'Manual refund recorded as completed.');
    }
}
