<?php

namespace App\Services;

use App\Contracts\Repositories\ReviewRepository;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function create(User $buyer, int $orderItemId, int $rating, ?string $comment): Review
    {
        return DB::transaction(function () use ($buyer, $orderItemId, $rating, $comment): Review {
            $orderItem = $this->reviews->findReviewableOrderItem($buyer, $orderItemId);
            $review = $this->reviews->save(new Review([
                'order_item_id' => $orderItem->id,
                'buyer_id' => $buyer->id,
                'seller_profile_id' => $orderItem->sellerOrder->seller_profile_id,
                'rating' => $rating,
                'comment' => $comment,
            ]));
            $this->auditLogs->record($buyer, 'review.created', $review, after: $review->getAttributes());

            return $review;
        });
    }
}
