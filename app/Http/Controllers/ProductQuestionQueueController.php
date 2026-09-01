<?php

namespace App\Http\Controllers;

use App\Services\ProductQuestionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductQuestionQueueController extends Controller
{
    public function __invoke(Request $request, ProductQuestionService $questions): Response
    {
        return Inertia::render('shared/product-questions', ['questions' => $questions->queueFor($request->user())]);
    }
}
