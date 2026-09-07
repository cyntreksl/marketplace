<?php

namespace App\Policies;

use App\Models\SellerOrder;
use App\Models\User;

class SellerOrderPolicy
{
    public function view(User $user, SellerOrder $sellerOrder): bool
    {
        return $user->sellerProfile()->whereKey($sellerOrder->seller_profile_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function updateStatus(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->view($user, $sellerOrder);
    }
}
