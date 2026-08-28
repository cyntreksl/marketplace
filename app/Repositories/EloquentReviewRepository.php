<?php

namespace App\Repositories;

use App\Contracts\Repositories\ReviewRepository;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EloquentReviewRepository implements ReviewRepository
{
    public function findReviewableOrderItem(User $buyer, int $orderItemId): OrderItem
    {
        $orderItem = OrderItem::query()
            ->with(['sellerOrder:id,customer_order_id,seller_profile_id,delivered_at'])
            ->whereKey($orderItemId)
            ->whereHas('sellerOrder', fn ($query) => $query
                ->whereNotNull('delivered_at')
                ->whereHas('customerOrder', fn ($query) => $query->where('buyer_id', $buyer->id)))
            ->lockForUpdate()
            ->firstOrFail();

        if ($orderItem->review()->exists()) {
            throw ValidationException::withMessages(['order_item' => 'You have already reviewed this delivered item.']);
        }

        return $orderItem;
    }

    public function save(Review $review): Review
    {
        $review->save();

        return $review;
    }

    public function recent(int $limit): Collection
    {
        return Review::query()
            ->with(['buyer:id,name', 'orderItem:id,listing_id,title', 'orderItem.listing:id,slug'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function forListing(int $listingId, int $limit): Collection
    {
        return Review::query()
            ->with('buyer:id,name')
            ->whereHas('orderItem', fn ($query) => $query->where('listing_id', $listingId))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function summary(): array
    {
        $average = Review::query()->avg('rating');

        return [
            'average' => $average === null ? null : (float) $average,
            'count' => Review::query()->count(),
        ];
    }
}
