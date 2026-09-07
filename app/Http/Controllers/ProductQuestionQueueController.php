<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductQuestionIndexRequest;
use App\Services\ProductQuestionService;
use Inertia\Inertia;
use Inertia\Response;

class ProductQuestionQueueController extends Controller
{
    public function __invoke(ProductQuestionIndexRequest $request, ProductQuestionService $questions): Response
    {
        return Inertia::render('shared/product-questions', [
            'questions' => $questions->queueFor($request->user(), $request->validated()),
            'filters' => ['q' => $request->validated('q', ''), 'status' => $request->validated('status', 'all')],
            'sellerPortal' => $request->user()->sellerProfile()->exists(),
        ]);
    }
}
