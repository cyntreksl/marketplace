<?php

namespace App\Repositories;

use App\Contracts\Repositories\PaymentAttemptRepository;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\PaymentAttemptStatus;

class EloquentPaymentAttemptRepository implements PaymentAttemptRepository
{
    public function lockPayment(int $paymentId): Payment
    {
        return Payment::withTrashed()->lockForUpdate()->findOrFail($paymentId);
    }

    public function pendingFor(Payment $payment): ?PaymentAttempt
    {
        return $payment->attempts()
            ->where('status', PaymentAttemptStatus::Pending)
            ->latest('attempt_number')
            ->first();
    }

    public function latestFor(Payment $payment): ?PaymentAttempt
    {
        return $payment->attempts()->latest('attempt_number')->first();
    }

    public function create(Payment $payment, array $data): PaymentAttempt
    {
        return $payment->attempts()->create($data);
    }

    public function save(PaymentAttempt $attempt, array $data): PaymentAttempt
    {
        $attempt->update($data);

        return $attempt->refresh();
    }
}
