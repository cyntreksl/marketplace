<?php

namespace App\Contracts\Repositories;

use App\Models\Cart;
use App\Models\User;

interface CartRepository
{
    public function forBuyer(User $buyer): Cart;
}
