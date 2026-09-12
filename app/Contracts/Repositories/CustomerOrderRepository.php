<?php

namespace App\Contracts\Repositories;

use App\Models\CustomerOrder;
use App\Models\User;

interface CustomerOrderRepository
{
    public function findForMetaConversion(int $id): ?CustomerOrder;

    public function withConfirmationDetails(CustomerOrder $customerOrder): CustomerOrder;

    public function withMetaConversionDetails(CustomerOrder $customerOrder): CustomerOrder;

    public function clearMetaAttribution(CustomerOrder $customerOrder): void;

    public function lockForClaim(int $id): CustomerOrder;

    public function claim(CustomerOrder $customerOrder, User $buyer): CustomerOrder;
}
