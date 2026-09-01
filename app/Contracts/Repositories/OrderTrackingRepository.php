<?php

namespace App\Contracts\Repositories;

use App\Models\CustomerOrder;

interface OrderTrackingRepository
{
    public function findForPublicLookup(string $number, string $email): ?CustomerOrder;
}
