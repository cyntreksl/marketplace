<?php

namespace App\Repositories;

use App\Contracts\Repositories\CartRepository;
use App\Models\Cart;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentCartRepository implements CartRepository
{
    public function forBuyer(User $buyer): Cart
    {
        $cart = Cart::withTrashed()->firstOrCreate(['buyer_id' => $buyer->id]);
        if ($cart->trashed()) {
            $cart->restore();
        }

        return $cart->load(['items.listing.media', 'items.listing.sellerProfile', 'items.variant.optionValues.option']);
    }

    public function listings(array $ids): Collection
    {
        return Listing::query()->directlyVisible()->with(['media', 'sellerProfile', 'variants.optionValues.option'])->whereIn('id', $ids)->get()->keyBy('id');
    }

    public function merge(User $buyer, array $items, string $token): void
    {
        DB::transaction(function () use ($buyer, $items, $token): void {
            $this->lock($buyer);
            if (! DB::table('cart_merges')->insertOrIgnore(['token' => $token, 'cart_id' => $this->forBuyer($buyer)->id, 'created_at' => now()])) {
                return;
            }
            foreach ($items as $item) {
                $this->setQuantity($buyer, $item['listing_id'], $item['listing_variant_id'], $item['selection_key'], $item['quantity'], true);
            }
        });
    }

    public function lock(User $buyer): void
    {
        $cart = $this->forBuyer($buyer);
        Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
    }

    public function setQuantity(User $buyer, int $listingId, ?int $variantId, string $selectionKey, int $quantity, bool $increment = false): void
    {
        DB::transaction(function () use ($buyer, $listingId, $variantId, $selectionKey, $quantity, $increment): void {
            $cart = $this->forBuyer($buyer);
            Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = $cart->items()->withTrashed()->firstOrNew(['listing_id' => $listingId, 'selection_key' => $selectionKey]);
            $item->forceFill(['quantity' => ($increment && $item->exists && ! $item->trashed() ? $item->quantity : 0) + $quantity, 'listing_variant_id' => $variantId, 'deleted_at' => null]);
            $item->save();
        });
    }

    public function remove(User $buyer, int $itemId): void
    {
        $this->forBuyer($buyer)->items()->findOrFail($itemId)->delete();
    }
}
