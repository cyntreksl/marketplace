<?php

namespace App\Contracts\Repositories;

use App\Models\Payment;
use App\Models\PaymentAttempt;

interface PaymentAttemptRepository
{
    public function lockPayment(int $paymentId): Payment;

    public function pendingFor(Payment $payment): ?PaymentAttempt;

    public function latestFor(Payment $payment): ?PaymentAttempt;

    /** @param array<string, mixed> $data */
    public function create(Payment $payment, array $data): PaymentAttempt;

    /** @param array<string, mixed> $data */
    public function save(PaymentAttempt $attempt, array $data): PaymentAttempt;
}
