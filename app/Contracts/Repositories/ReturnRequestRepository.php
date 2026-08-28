<?php

namespace App\Contracts\Repositories;

use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReturnRequestRepository
{
    /** @return LengthAwarePaginator<int, OrderItem> */
    public function buyerItems(User $buyer): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, ReturnRequest> */
    public function buyerRequests(User $buyer): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, ReturnRequest> */
    public function sellerRequests(User $seller): LengthAwarePaginator;

    public function lockOrderItemForBuyer(int $orderItemId, User $buyer): ?OrderItem;

    public function claimedQuantity(int $orderItemId): int;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ReturnRequest;

    public function lockRequestForSeller(int $returnRequestId, User $seller): ?ReturnRequest;

    public function lockSellerOrderForSeller(int $sellerOrderId, User $seller): ?SellerOrder;

    public function save(ReturnRequest $returnRequest): ReturnRequest;

    public function findWithContext(int $returnRequestId): ?ReturnRequest;
}
