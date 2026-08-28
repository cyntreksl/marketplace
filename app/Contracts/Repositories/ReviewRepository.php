<?php

namespace App\Contracts\Repositories;

use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

interface ReviewRepository
{
    public function findReviewableOrderItem(User $buyer, int $orderItemId): OrderItem;

    public function save(Review $review): Review;

    /** @return Collection<int, Review> */
    public function recent(int $limit): Collection;

    /** @return Collection<int, Review> */
    public function forListing(int $listingId, int $limit): Collection;

    /** @return array{average: float|null, count: int} */
    public function summary(): array;
}
