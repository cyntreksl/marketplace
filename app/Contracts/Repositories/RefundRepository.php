<?php

namespace App\Contracts\Repositories;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RefundRepository
{
    /** @return LengthAwarePaginator<int, ReturnRequest> */
    public function operationsQueue(): LengthAwarePaginator;

    public function lockRequest(int $returnRequestId): ?ReturnRequest;

    public function paymentFor(ReturnRequest $returnRequest): ?Payment;

    /** @param array<string, mixed> $attributes */
    public function createRefund(array $attributes): Refund;

    public function saveReturn(ReturnRequest $returnRequest): ReturnRequest;

    public function saveRefund(Refund $refund): Refund;

    public function withContext(Refund $refund): Refund;

    /** @return Collection<int, User> */
    public function operationsUsers(): Collection;
}
