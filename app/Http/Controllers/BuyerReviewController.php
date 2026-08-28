<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\OrderItem;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;

class BuyerReviewController extends Controller
{
    public function store(StoreReviewRequest $request, OrderItem $orderItem, ReviewService $reviews): RedirectResponse
    {
        $reviews->create(
            $request->user(),
            (int) $orderItem->getKey(),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return back()->with('status', 'Thanks for sharing your verified review.');
    }
}
