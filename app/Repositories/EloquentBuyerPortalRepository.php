<?php

namespace App\Repositories;

use App\BuyerOrderStage;
use App\Contracts\Repositories\BuyerPortalRepository;
use App\Models\CustomerOrder;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\User;
use App\PaymentAttemptStatus;
use App\ReturnStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentBuyerPortalRepository implements BuyerPortalRepository
{
    public function orderCounts(User $buyer): array
    {
        $counts = [];

        foreach (BuyerOrderStage::cases() as $stage) {
            $counts[$stage->value] = $this->orderQuery($buyer, $stage)->count();
        }

        return $counts;
    }

    public function orders(User $buyer, BuyerOrderStage $stage): LengthAwarePaginator
    {
        return $this->orderQuery($buyer, $stage)
            ->with($this->orderSummaryRelations())
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    public function order(User $buyer, CustomerOrder $customerOrder): CustomerOrder
    {
        return CustomerOrder::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->with([
                'sellerOrders.sellerProfile:id,store_name',
                'sellerOrders.shipment',
                'sellerOrders.items.listing.media',
                'sellerOrders.items.review',
                'sellerOrders.items.returnRequests.refund',
                'payments.attempts',
            ])
            ->findOrFail($customerOrder->id);
    }

    public function recentOrders(User $buyer, int $limit): Collection
    {
        return CustomerOrder::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->with($this->orderSummaryRelations())
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function paymentAttempts(User $buyer, string $status): LengthAwarePaginator
    {
        return $this->paymentAttemptQuery($buyer, $status)
            ->with('payment.customerOrder:id,number,buyer_id,status,total')
            ->latest('attempted_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function recentPaymentAttempts(User $buyer, int $limit): Collection
    {
        return $this->paymentAttemptQuery($buyer, 'all')
            ->with('payment.customerOrder:id,number,buyer_id,status,total')
            ->latest('attempted_at')
            ->limit($limit)
            ->get();
    }

    public function submittedFeedback(User $buyer): LengthAwarePaginator
    {
        return Review::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->with(['orderItem:id,seller_order_id,listing_id,title,variant_options', 'orderItem.listing.media', 'orderItem.sellerOrder:id,number,seller_profile_id', 'orderItem.sellerOrder.sellerProfile:id,store_name'])
            ->latest()
            ->paginate(16)
            ->withQueryString();
    }

    public function awaitingFeedback(User $buyer): LengthAwarePaginator
    {
        return $this->awaitingFeedbackQuery($buyer)
            ->with(['listing.media', 'sellerOrder:id,number,customer_order_id,seller_profile_id,delivered_at', 'sellerOrder.sellerProfile:id,store_name'])
            ->latest('order_items.id')
            ->paginate(16)
            ->withQueryString();
    }

    public function pendingFeedbackCount(User $buyer): int
    {
        return $this->awaitingFeedbackQuery($buyer)->count();
    }

    public function activeReturnCount(User $buyer): int
    {
        return ReturnRequest::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->whereNotIn('status', [ReturnStatus::Rejected->value, ReturnStatus::Refunded->value])
            ->count();
    }

    /** @return Builder<CustomerOrder> */
    private function orderQuery(User $buyer, BuyerOrderStage $stage): Builder
    {
        $query = CustomerOrder::query()->whereBelongsTo($buyer, 'buyer');

        return match ($stage) {
            BuyerOrderStage::All => $query,
            BuyerOrderStage::ToPay => $query->where('status', 'pending_payment'),
            BuyerOrderStage::Processing => $query
                ->where('status', 'confirmed')
                ->whereHas('sellerOrders', fn (Builder $query): Builder => $query->whereIn('status', ['paid', 'processing', 'ready_to_ship'])),
            BuyerOrderStage::Shipped => $query
                ->where('status', 'confirmed')
                ->whereHas('sellerOrders', fn (Builder $query): Builder => $query->where('status', 'shipped'))
                ->whereDoesntHave('sellerOrders', fn (Builder $query): Builder => $query->whereNotIn('status', ['shipped', 'completed'])),
            BuyerOrderStage::Completed => $query
                ->where('status', 'confirmed')
                ->whereHas('sellerOrders')
                ->whereDoesntHave('sellerOrders', fn (Builder $query): Builder => $query->where('status', '!=', 'completed')),
            BuyerOrderStage::Archived => $query->whereIn('status', ['expired', 'cancelled']),
        };
    }

    /** @return array<int, string> */
    private function orderSummaryRelations(): array
    {
        return [
            'sellerOrders.sellerProfile:id,store_name',
            'sellerOrders.shipment:id,seller_order_id,status,tracking_number,courier_name',
            'sellerOrders.items.listing.media',
            'sellerOrders.items.review:id,order_item_id,rating,comment',
            'payments.attempts',
        ];
    }

    /** @return Builder<PaymentAttempt> */
    private function paymentAttemptQuery(User $buyer, string $status): Builder
    {
        $query = PaymentAttempt::query()
            ->whereHas('payment.customerOrder', fn (Builder $query): Builder => $query->where('buyer_id', $buyer->id));

        return match ($status) {
            'pending' => $query->where('status', PaymentAttemptStatus::Pending),
            'successful' => $query->where('status', PaymentAttemptStatus::Succeeded),
            'failed' => $query->whereIn('status', [PaymentAttemptStatus::Failed, PaymentAttemptStatus::Expired]),
            default => $query,
        };
    }

    /** @return Builder<OrderItem> */
    private function awaitingFeedbackQuery(User $buyer): Builder
    {
        return OrderItem::query()
            ->whereDoesntHave('review')
            ->whereHas('sellerOrder', fn (Builder $query): Builder => $query
                ->whereNotNull('delivered_at')
                ->whereHas('customerOrder', fn (Builder $query): Builder => $query->where('buyer_id', $buyer->id)));
    }
}
