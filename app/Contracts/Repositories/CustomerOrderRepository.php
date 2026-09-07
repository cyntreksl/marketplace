<?php

namespace App\Contracts\Repositories;

use App\Models\CustomerOrder;

interface CustomerOrderRepository
{
    public function findForMetaConversion(int $id): ?CustomerOrder;

    public function withConfirmationDetails(CustomerOrder $customerOrder): CustomerOrder;

    public function withMetaConversionDetails(CustomerOrder $customerOrder): CustomerOrder;

    public function clearMetaAttribution(CustomerOrder $customerOrder): void;
}
