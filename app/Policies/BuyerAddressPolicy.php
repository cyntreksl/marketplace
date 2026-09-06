<?php

namespace App\Policies;

use App\Models\BuyerAddress;
use App\Models\User;

class BuyerAddressPolicy
{
    public function update(User $user, BuyerAddress $buyerAddress): bool
    {
        return $buyerAddress->buyer_id === $user->id;
    }

    public function delete(User $user, BuyerAddress $buyerAddress): bool
    {
        return $buyerAddress->buyer_id === $user->id;
    }
}
