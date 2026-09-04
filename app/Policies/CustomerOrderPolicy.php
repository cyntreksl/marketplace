<?php

namespace App\Policies;

use App\Models\CustomerOrder;
use App\Models\User;

class CustomerOrderPolicy
{
    public function view(User $user, CustomerOrder $customerOrder): bool
    {
        return $customerOrder->buyer_id === $user->id;
    }
}
