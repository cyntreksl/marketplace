<?php

namespace App\Contracts\Repositories;

use App\Models\CustomerOrder;

interface CustomerOrderRepository
{
    public function withConfirmationDetails(CustomerOrder $customerOrder): CustomerOrder;
}
