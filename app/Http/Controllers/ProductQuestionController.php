<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerProductQuestionRequest;
use App\Http\Requests\StoreProductQuestionRequest;
use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Services\ProductQuestionService;
use Illuminate\Http\RedirectResponse;

class ProductQuestionController extends Controller
{
    public function store(StoreProductQuestionRequest $request, Listing $listing, ProductQuestionService $questions): RedirectResponse
    {
        $questions->ask($request->user(), $listing, $request->validated('question'));

        return back()->with('toast', ['type' => 'success', 'message' => 'Your question was sent to the seller.']);
    }

    public function update(AnswerProductQuestionRequest $request, ProductQuestion $question, ProductQuestionService $questions): RedirectResponse
    {
        $questions->answer($request->user(), $question, $request->validated('answer'));

        return back()->with('toast', ['type' => 'success', 'message' => 'Answer published.']);
    }
}
