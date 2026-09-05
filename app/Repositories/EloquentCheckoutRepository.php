<?php

namespace App\Repositories;

use App\Contracts\Repositories\CheckoutRepository;
use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Collection;

class EloquentCheckoutRepository implements CheckoutRepository
{
    public function cart(User $buyer): Cart
    {
        $cart = Cart::query()->where('buyer_id', $buyer->id)->lockForUpdate()->firstOrFail();
        $cart->setRelation('items', $cart->items()->with(['listing.sellerProfile', 'variant.optionValues.option'])->orderBy('listing_id')->orderBy('listing_variant_id')->lockForUpdate()->get());

        return $cart;
    }

    public function listing(int $id): Listing
    {
        return Listing::query()->directlyVisible()->lockForUpdate()->findOrFail($id);
    }

    public function variant(int $id): ?ListingVariant
    {
        return ListingVariant::query()->with('optionValues.option')->lockForUpdate()->find($id);
    }

    public function findSubmission(User $buyer, string $token): ?CustomerOrder
    {
        return CustomerOrder::query()->where('buyer_id', $buyer->id)->where('checkout_token', $token)->first();
    }

    public function createOrder(array $data): CustomerOrder
    {
        $order = CustomerOrder::query()->create($data);
        $order->update(['number' => 'PRO'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

        return $order;
    }

    public function createSellerOrder(array $data): SellerOrder
    {
        return SellerOrder::query()->create($data);
    }

    public function createPayment(array $data): Payment
    {
        return Payment::query()->create($data);
    }

    public function addItem(SellerOrder $order, array $data): void
    {
        $order->items()->create($data);
    }

    public function reserve(Listing $listing, ?ListingVariant $variant, int $quantity): void
    {
        $listing->increment('reserved_quantity', $quantity);
        $variant?->increment('reserved_quantity', $quantity);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function details(CustomerOrder $order): CustomerOrder
    {
        return $order->load(['sellerOrders.items', 'payments']);
    }

    public function payment(CustomerOrder $order): Payment
    {
        return $order->payments()->where('method', 'stripe')->firstOrFail();
    }

    public function lockPayment(int $id): Payment
    {
        return Payment::query()->lockForUpdate()->with('customerOrder.buyer')->findOrFail($id);
    }

    public function savePayment(Payment $payment, array $data): void
    {
        $payment->forceFill($data)->save();
    }

    public function confirm(CustomerOrder $order): void
    {
        $order->update(['status' => 'confirmed']);
        $order->sellerOrders()->where('status', 'pending_payment')->update(['status' => 'paid']);
    }

    public function expire(CustomerOrder $order): void
    {
        $order->update(['status' => 'expired']);
        foreach ($order->sellerOrders()->with('items')->get() as $sellerOrder) {
            foreach ($sellerOrder->items as $item) {
                Listing::withTrashed()->whereKey($item->listing_id)->decrement('reserved_quantity', $item->quantity);
                if ($item->listing_variant_id !== null) {
                    ListingVariant::query()->whereKey($item->listing_variant_id)->decrement('reserved_quantity', $item->quantity);
                }
            }
            $sellerOrder->update(['status' => 'expired']);
        }
    }

    public function findPayment(int $id): ?Payment
    {
        return Payment::query()->find($id);
    }

    public function duePayments(): Collection
    {
        return Payment::query()->where('method', 'stripe')->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->limit(100)->get();
    }
}
