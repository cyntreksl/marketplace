<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\PayoutRequest;
use App\Models\ProductQuestion;
use App\Models\SellerLedgerEntry;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SellerPortalRepository
{
    public function profile(User $seller): SellerProfile;

    /**
     * @param  array{q?: string, status?: string, sort?: string}  $filters
     * @return LengthAwarePaginator<int, SellerOrder>
     */
    public function orders(User $seller, array $filters, int $perPage = 15): LengthAwarePaginator;

    /** @return array<string, int> */
    public function orderCounts(User $seller): array;

    public function order(User $seller, SellerOrder $sellerOrder): SellerOrder;

    public function lockOrder(User $seller, int $sellerOrderId): ?SellerOrder;

    /** @return array<string, int|string> */
    public function metrics(User $seller): array;

    /** @return Collection<int, SellerOrder> */
    public function recentOrders(User $seller, int $limit): Collection;

    /** @return Collection<int, Listing> */
    public function lowStockProducts(User $seller, int $limit): Collection;

    /** @return Collection<int, ProductQuestion> */
    public function unansweredQuestions(User $seller, int $limit): Collection;

    /** @return LengthAwarePaginator<int, SellerLedgerEntry> */
    public function transactions(User $seller, int $perPage = 20): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, PayoutRequest> */
    public function payouts(User $seller, int $perPage = 10): LengthAwarePaginator;
}
