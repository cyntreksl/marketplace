<?php

namespace App\Repositories;

use App\Contracts\Repositories\CartRepository;
use App\Models\Cart;
use App\Models\User;

class EloquentCartRepository implements CartRepository
{
    public function forBuyer(User $buyer): Cart
    {
        return Cart::query()
            ->firstOrCreate(['buyer_id' => $buyer->id])
            ->load([
                'items.listing.media',
                'items.listing.sellerProfile',
                'items.variant.optionValues.option',
            ]);
    }
}
