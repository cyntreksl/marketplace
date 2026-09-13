<?php

namespace App\Contracts\Repositories;

use App\Models\CustomerOrder;
use App\Models\User;
use App\Support\MetaConversionReceipt;

interface CustomerOrderRepository
{
    public function findForMetaConversion(int $id): ?CustomerOrder;

    public function withConfirmationDetails(CustomerOrder $customerOrder): CustomerOrder;

    public function withMetaConversionDetails(CustomerOrder $customerOrder): CustomerOrder;

    public function markMetaPurchaseDelivered(CustomerOrder $customerOrder, MetaConversionReceipt $receipt): void;

    public function lockForClaim(int $id): CustomerOrder;

    public function claim(CustomerOrder $customerOrder, User $buyer): CustomerOrder;
}
