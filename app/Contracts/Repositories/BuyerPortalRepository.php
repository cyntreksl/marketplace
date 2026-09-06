<?php

namespace App\Contracts\Repositories;

use App\BuyerOrderStage;
use App\Models\CustomerOrder;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Review;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BuyerPortalRepository
{
    /** @return array<string, int> */
    public function orderCounts(User $buyer): array;

    /** @return LengthAwarePaginator<int, CustomerOrder> */
    public function orders(User $buyer, BuyerOrderStage $stage): LengthAwarePaginator;

    public function order(User $buyer, CustomerOrder $customerOrder): CustomerOrder;

    /** @return Collection<int, CustomerOrder> */
    public function recentOrders(User $buyer, int $limit): Collection;

    /** @return LengthAwarePaginator<int, PaymentAttempt> */
    public function paymentAttempts(User $buyer, string $status): LengthAwarePaginator;

    /** @return Collection<int, PaymentAttempt> */
    public function recentPaymentAttempts(User $buyer, int $limit): Collection;

    /** @return LengthAwarePaginator<int, OrderItem> */
    public function awaitingFeedback(User $buyer): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Review> */
    public function submittedFeedback(User $buyer): LengthAwarePaginator;

    public function pendingFeedbackCount(User $buyer): int;

    public function activeReturnCount(User $buyer): int;
}
