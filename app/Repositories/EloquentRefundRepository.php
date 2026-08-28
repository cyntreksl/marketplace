<?php

namespace App\Repositories;

use App\Contracts\Repositories\RefundRepository;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use App\ReturnStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentRefundRepository implements RefundRepository
{
    public function operationsQueue(): LengthAwarePaginator
    {
        return ReturnRequest::query()
            ->whereIn('status', [
                ReturnStatus::Approved,
                ReturnStatus::RefundPending,
                ReturnStatus::RefundFailed,
                ReturnStatus::Refunded,
            ])
            ->with([
                'buyer:id,name,email',
                'orderItem:id,seller_order_id,title,unit_price',
                'orderItem.sellerOrder:id,number,customer_order_id,seller_profile_id',
                'orderItem.sellerOrder.sellerProfile:id,store_name',
                'refund.payment:id,method,provider_reference,status',
            ])
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    public function lockRequest(int $returnRequestId): ?ReturnRequest
    {
        return ReturnRequest::query()
            ->with([
                'buyer:id,name,email',
                'orderItem:id,seller_order_id,title,unit_price',
                'orderItem.sellerOrder:id,number,customer_order_id,seller_profile_id',
                'orderItem.sellerOrder.sellerProfile:id,store_name',
                'refund.payment',
            ])
            ->lockForUpdate()
            ->find($returnRequestId);
    }

    public function paymentFor(ReturnRequest $returnRequest): ?Payment
    {
        return Payment::query()
            ->where('customer_order_id', $returnRequest->orderItem->sellerOrder->customer_order_id)
            ->latest('id')
            ->first();
    }

    public function createRefund(array $attributes): Refund
    {
        return Refund::query()->create($attributes);
    }

    public function saveReturn(ReturnRequest $returnRequest): ReturnRequest
    {
        $returnRequest->save();

        return $returnRequest->refresh();
    }

    public function saveRefund(Refund $refund): Refund
    {
        $refund->save();

        return $refund->refresh();
    }

    public function withContext(Refund $refund): Refund
    {
        return $refund->loadMissing('returnRequest.buyer', 'returnRequest.orderItem');
    }

    public function operationsUsers(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [Role::Admin, Role::FinanceAdmin, Role::SuperAdmin]))
            ->get();
    }
}
