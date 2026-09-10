<?php

namespace App\Repositories;

use App\Contracts\Repositories\SellerPortalRepository;
use App\Models\Listing;
use App\Models\PayoutRequest;
use App\Models\ProductQuestion;
use App\Models\ReturnRequest;
use App\Models\SellerLedgerEntry;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use App\ReturnStatus;
use App\SellerOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentSellerPortalRepository implements SellerPortalRepository
{
    public function profile(User $seller): SellerProfile
    {
        return SellerProfile::query()->whereBelongsTo($seller)->firstOrFail();
    }

    /**
     * @param  array{q?: string, status?: string, sort?: string}  $filters
     * @return LengthAwarePaginator<int, SellerOrder>
     */
    public function orders(User $seller, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->orderQuery($seller)
            ->with(['customerOrder:id,number,buyer_id,auction_offer_id,shipping_address', 'customerOrder.buyer:id,name', 'items:id,seller_order_id,title,quantity,variant_options', 'shipment:id,seller_order_id,courier_name,tracking_number,status', 'refund.processor:id,name,email', 'cancelledBy:id,name,email']);

        if (($filters['status'] ?? 'all') !== 'all') {
            $status = $filters['status'];
            if ($status === 'archived') {
                $query->whereIn('status', [SellerOrderStatus::Expired, SellerOrderStatus::Cancelled]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customerOrder', fn (Builder $order): Builder => $order
                        ->where('number', 'like', "%{$search}%")
                        ->orWhere('shipping_address->name', 'like', "%{$search}%")
                        ->orWhere('shipping_address->recipient_name', 'like', "%{$search}%"))
                    ->orWhereHas('shipment', fn (Builder $shipment): Builder => $shipment->where('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('items', fn (Builder $items): Builder => $items->where('title', 'like', "%{$search}%"));
            });
        }

        $sort = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy('created_at', $sort)->paginate($perPage)->withQueryString();
    }

    public function orderCounts(User $seller): array
    {
        $base = $this->orderQuery($seller);
        $counts = $base->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'pending_payment' => (int) ($counts['pending_payment'] ?? 0),
            'paid' => (int) ($counts['paid'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'ready_to_ship' => (int) ($counts['ready_to_ship'] ?? 0),
            'shipped' => (int) ($counts['shipped'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'archived' => (int) ($counts['expired'] ?? 0) + (int) ($counts['cancelled'] ?? 0),
        ];
    }

    public function order(User $seller, SellerOrder $sellerOrder): SellerOrder
    {
        return $this->orderQuery($seller)
            ->with(['items.listing.media', 'shipment', 'refund.processor:id,name,email', 'cancelledBy:id,name,email', 'customerOrder.payments', 'customerOrder.buyer:id,name'])
            ->findOrFail($sellerOrder->id);
    }

    public function lockOrder(User $seller, int $sellerOrderId): ?SellerOrder
    {
        return $this->orderQuery($seller)
            ->with(['shipment', 'customerOrder.buyer:id,name'])
            ->lockForUpdate()
            ->find($sellerOrderId);
    }

    public function metrics(User $seller): array
    {
        $profile = $this->profile($seller);
        $counts = $this->orderCounts($seller);
        $monthlyEarnings = $this->orderQuery($seller)
            ->where('status', SellerOrderStatus::Completed)
            ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('seller_earnings');
        $availableBalance = $profile->ledgerEntries()->where('status', 'available')->sum('amount');

        return [
            'monthly_completed_earnings' => number_format((float) $monthlyEarnings, 2, '.', ''),
            'orders_needing_action' => $counts['paid'] + $counts['processing'],
            'ready_to_dispatch' => $counts['ready_to_ship'],
            'in_transit' => $counts['shipped'],
            'active_products' => $profile->listings()->where('is_active', true)->where('status', 'approved')->count(),
            'open_returns' => ReturnRequest::query()->whereHas('orderItem.sellerOrder', fn (Builder $query): Builder => $query->where('seller_profile_id', $profile->id))->whereNotIn('status', [ReturnStatus::Rejected, ReturnStatus::Refunded])->count(),
            'available_balance' => (string) $availableBalance,
        ];
    }

    public function recentOrders(User $seller, int $limit): Collection
    {
        return $this->orderQuery($seller)->with(['customerOrder:id,number,buyer_id,auction_offer_id,shipping_address', 'customerOrder.buyer:id,name', 'items:id,seller_order_id,title,quantity', 'shipment:id,seller_order_id,courier_name,tracking_number,status', 'refund.processor:id,name,email', 'cancelledBy:id,name,email'])->latest()->limit($limit)->get();
    }

    public function lowStockProducts(User $seller, int $limit): Collection
    {
        $profile = $this->profile($seller);

        return Listing::query()->whereBelongsTo($profile, 'sellerProfile')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity')->limit($limit)->get(['id', 'title', 'stock_quantity', 'reserved_quantity', 'low_stock_threshold']);
    }

    public function unansweredQuestions(User $seller, int $limit): Collection
    {
        $profile = $this->profile($seller);

        return ProductQuestion::query()->whereNull('answered_at')->whereHas('listing', fn (Builder $query): Builder => $query->where('seller_profile_id', $profile->id))->with(['listing:id,title', 'asker:id,name'])->latest()->limit($limit)->get();
    }

    /** @return LengthAwarePaginator<int, SellerLedgerEntry> */
    public function transactions(User $seller, int $perPage = 20): LengthAwarePaginator
    {
        return $this->profile($seller)->ledgerEntries()->latest()->paginate($perPage, pageName: 'transactions_page')->withQueryString();
    }

    /** @return LengthAwarePaginator<int, PayoutRequest> */
    public function payouts(User $seller, int $perPage = 10): LengthAwarePaginator
    {
        return $this->profile($seller)->payoutRequests()->latest()->paginate($perPage, pageName: 'payouts_page')->withQueryString();
    }

    /** @return Builder<SellerOrder> */
    private function orderQuery(User $seller): Builder
    {
        return SellerOrder::query()->whereHas('sellerProfile', fn (Builder $query): Builder => $query->where('user_id', $seller->id));
    }
}
