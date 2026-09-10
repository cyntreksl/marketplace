<?php

namespace App\Repositories;

use App\Contracts\Repositories\OrderOperationsRepository;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

class EloquentOrderOperationsRepository implements OrderOperationsRepository
{
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->adminQuery($filters)
            ->select(['id', 'number', 'buyer_id', 'status', 'total', 'created_at'])
            ->with([
                'buyer:id,name,email',
                'sellerOrders:id,number,customer_order_id,seller_profile_id,status',
                'sellerOrders.sellerProfile:id,store_name',
                'payments:id,customer_order_id,method,status,amount',
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function lazyForAdminExport(array $filters): LazyCollection
    {
        return $this->adminQuery($filters)
            ->select(['id', 'number', 'buyer_id', 'status', 'subtotal', 'shipping_total', 'total', 'shipping_address', 'created_at', 'updated_at'])
            ->with([
                'buyer:id,name,email',
                'payments:id,customer_order_id,method,status,paid_at',
                'sellerOrders:id,number,customer_order_id,seller_profile_id,status',
                'sellerOrders.sellerProfile:id,store_name',
                'sellerOrders.items:id,seller_order_id,title,quantity',
            ])
            ->lazy(200);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CustomerOrder>
     */
    private function adminQuery(array $filters): Builder
    {
        $query = CustomerOrder::query();

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn (Builder $buyer): Builder => $buyer
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('sellerOrders', fn (Builder $sellerOrders): Builder => $sellerOrders
                        ->where('number', 'like', "%{$search}%")
                        ->orWhereHas('sellerProfile', fn (Builder $seller): Builder => $seller->where('store_name', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn (Builder $items): Builder => $items->where('title', 'like', "%{$search}%")));
            });
        }

        if (($filters['payment_method'] ?? 'all') !== 'all' || ($filters['payment_status'] ?? 'all') !== 'all') {
            $query->whereHas('payments', function (Builder $payments) use ($filters): void {
                $payments
                    ->when(($filters['payment_method'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('method', $filters['payment_method']))
                    ->when(($filters['payment_status'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('status', $filters['payment_status']));
            });
        }

        $query
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', $date.' 23:59:59'));

        if (($filters['sort'] ?? 'newest') === 'oldest') {
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        return $query;
    }

    public function findDetailedForAdmin(CustomerOrder $customerOrder): CustomerOrder
    {
        return CustomerOrder::query()
            ->with([
                'buyer:id,name,email',
                'payments:id,customer_order_id,method,status,amount,paid_at',
                'sellerOrders.sellerProfile:id,user_id,store_name',
                'sellerOrders.items.listing.media',
                'sellerOrders.shipment',
                'sellerOrders.refund:id,seller_order_id,payment_id,method,amount,status,manual_reference,processed_by,completed_at',
                'sellerOrders.refund.processor:id,name,email',
                'sellerOrders.cancelledBy:id,name,email',
            ])
            ->findOrFail($customerOrder->id);
    }

    public function lockSellerOrder(int $sellerOrderId): ?SellerOrder
    {
        return SellerOrder::query()
            ->with([
                'items:id,seller_order_id,listing_id,listing_variant_id,title,quantity',
                'customerOrder.sellerOrders:id,customer_order_id,status,subtotal,shipping_charge',
                'customerOrder.payments.refunds',
                'customerOrder.buyer:id,name,email',
                'sellerProfile:id,user_id,store_name',
                'shipment',
                'refund',
            ])
            ->lockForUpdate()
            ->find($sellerOrderId);
    }

    public function lockPayment(int $paymentId): Payment
    {
        return Payment::query()->with('refunds')->lockForUpdate()->findOrFail($paymentId);
    }

    public function releaseInventory(SellerOrder $sellerOrder): void
    {
        foreach ($sellerOrder->items as $item) {
            if ($item->listing_id !== null) {
                $listing = Listing::withTrashed()->lockForUpdate()->find($item->listing_id);
                $listing?->forceFill([
                    'reserved_quantity' => max(0, $listing->reserved_quantity - $item->quantity),
                ])->save();
            }

            if ($item->listing_variant_id !== null) {
                $variant = ListingVariant::query()->lockForUpdate()->find($item->listing_variant_id);
                $variant?->forceFill([
                    'reserved_quantity' => max(0, $variant->reserved_quantity - $item->quantity),
                ])->save();
            }
        }
    }

    public function saveSellerOrder(SellerOrder $sellerOrder): SellerOrder
    {
        $sellerOrder->save();

        return $sellerOrder;
    }

    public function saveCustomerOrder(CustomerOrder $customerOrder): CustomerOrder
    {
        $customerOrder->save();

        return $customerOrder;
    }

    public function savePayment(Payment $payment): Payment
    {
        $payment->save();

        return $payment;
    }

    public function createRefund(array $attributes): Refund
    {
        return Refund::query()->create($attributes);
    }

    public function saveRefund(Refund $refund): Refund
    {
        $refund->save();

        return $refund;
    }
}
