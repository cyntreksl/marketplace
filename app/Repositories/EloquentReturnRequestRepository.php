<?php

namespace App\Repositories;

use App\Contracts\Repositories\ReturnRequestRepository;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentReturnRequestRepository implements ReturnRequestRepository
{
    public function buyerItems(User $buyer): LengthAwarePaginator
    {
        return OrderItem::query()
            ->whereHas('sellerOrder.customerOrder', fn ($query) => $query->where('buyer_id', $buyer->id))
            ->withSum('returnRequests as claimed_quantity', 'quantity')
            ->with(['sellerOrder:id,number,customer_order_id,seller_profile_id,status,delivered_at', 'sellerOrder.sellerProfile:id,store_name'])
            ->latest('id')
            ->paginate(25, ['id', 'seller_order_id', 'title', 'quantity', 'unit_price', 'total'], 'items_page')
            ->withQueryString();
    }

    public function buyerRequests(User $buyer): LengthAwarePaginator
    {
        return ReturnRequest::query()
            ->where('buyer_id', $buyer->id)
            ->with(['orderItem:id,seller_order_id,title,unit_price', 'orderItem.sellerOrder:id,number,seller_profile_id', 'orderItem.sellerOrder.sellerProfile:id,store_name', 'refund'])
            ->latest()
            ->paginate(15, pageName: 'returns_page')
            ->withQueryString();
    }

    public function sellerRequests(User $seller): LengthAwarePaginator
    {
        return ReturnRequest::query()
            ->whereHas('orderItem.sellerOrder.sellerProfile', fn ($query) => $query->where('user_id', $seller->id))
            ->with(['buyer:id,name,email', 'orderItem:id,seller_order_id,title,unit_price', 'orderItem.sellerOrder:id,number,seller_profile_id', 'orderItem.sellerOrder.sellerProfile:id,store_name', 'refund'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function lockOrderItemForBuyer(int $orderItemId, User $buyer): ?OrderItem
    {
        return OrderItem::query()
            ->whereKey($orderItemId)
            ->whereHas('sellerOrder.customerOrder', fn ($query) => $query->where('buyer_id', $buyer->id))
            ->with(['sellerOrder:id,customer_order_id,status,delivered_at'])
            ->lockForUpdate()
            ->first();
    }

    public function claimedQuantity(int $orderItemId): int
    {
        return (int) ReturnRequest::query()->where('order_item_id', $orderItemId)->sum('quantity');
    }

    public function create(array $attributes): ReturnRequest
    {
        return ReturnRequest::query()->create($attributes);
    }

    public function lockRequestForSeller(int $returnRequestId, User $seller): ?ReturnRequest
    {
        return ReturnRequest::query()
            ->whereKey($returnRequestId)
            ->whereHas('orderItem.sellerOrder.sellerProfile', fn ($query) => $query->where('user_id', $seller->id))
            ->with(['buyer:id,name,email', 'orderItem:id,seller_order_id,title', 'orderItem.sellerOrder:id,number,seller_profile_id', 'orderItem.sellerOrder.sellerProfile:id,store_name'])
            ->lockForUpdate()
            ->first();
    }

    public function lockSellerOrderForSeller(int $sellerOrderId, User $seller): ?SellerOrder
    {
        return SellerOrder::query()
            ->whereKey($sellerOrderId)
            ->whereHas('sellerProfile', fn ($query) => $query->where('user_id', $seller->id))
            ->with('shipment')
            ->lockForUpdate()
            ->first();
    }

    public function save(ReturnRequest $returnRequest): ReturnRequest
    {
        $returnRequest->save();

        return $returnRequest->refresh();
    }

    public function findWithContext(int $returnRequestId): ?ReturnRequest
    {
        return ReturnRequest::query()
            ->with(['orderItem.sellerOrder.sellerProfile', 'buyer', 'refund'])
            ->find($returnRequestId);
    }

    public function sellerFor(ReturnRequest $returnRequest): ?User
    {
        return SellerProfile::query()
            ->whereHas('sellerOrders.items.returnRequests', fn ($query) => $query->whereKey($returnRequest->id))
            ->with('user')
            ->first()
            ?->user;
    }

    public function operationsUsers(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [Role::Admin, Role::FinanceAdmin, Role::SuperAdmin]))
            ->get();
    }
}
