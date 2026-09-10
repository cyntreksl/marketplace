<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\User;

class SellerOrderPolicy
{
    public function view(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->belongsToSeller($user, $sellerOrder) || $this->isOperationsUser($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function updateStatus(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->view($user, $sellerOrder);
    }

    public function cancel(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->view($user, $sellerOrder);
    }

    private function belongsToSeller(User $user, SellerOrder $sellerOrder): bool
    {
        return $user->sellerProfile()->whereKey($sellerOrder->seller_profile_id)->exists();
    }

    private function isOperationsUser(User $user): bool
    {
        return $user->roles()->whereIn('name', [Role::Admin, Role::SuperAdmin])->exists();
    }
}
