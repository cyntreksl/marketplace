<?php

namespace App\Contracts\Repositories;

use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SellerOrder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface OrderOperationsRepository
{
    /**
     * @param  array{search?: string, status?: string, sort?: string}  $filters
     * @return LengthAwarePaginator<int, CustomerOrder>
     */
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, CustomerOrder>
     */
    public function lazyForAdminExport(array $filters): LazyCollection;

    public function findDetailedForAdmin(CustomerOrder $customerOrder): CustomerOrder;

    public function lockSellerOrder(int $sellerOrderId): ?SellerOrder;

    public function lockPayment(int $paymentId): Payment;

    public function releaseInventory(SellerOrder $sellerOrder): void;

    public function saveSellerOrder(SellerOrder $sellerOrder): SellerOrder;

    public function saveCustomerOrder(CustomerOrder $customerOrder): CustomerOrder;

    public function savePayment(Payment $payment): Payment;

    /** @param array<string, mixed> $attributes */
    public function createRefund(array $attributes): Refund;

    public function saveRefund(Refund $refund): Refund;
}
