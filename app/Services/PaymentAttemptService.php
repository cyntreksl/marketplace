<?php

namespace App\Services;

use App\Contracts\Repositories\PaymentAttemptRepository;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\PaymentAttemptStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentAttemptService
{
    public function __construct(private readonly PaymentAttemptRepository $attempts) {}

    public function begin(Payment $payment): PaymentAttempt
    {
        return DB::transaction(function () use ($payment): PaymentAttempt {
            $lockedPayment = $this->attempts->lockPayment($payment->id);
            $pending = $this->attempts->pendingFor($lockedPayment);

            if ($pending !== null) {
                return $pending;
            }

            $nextNumber = ($this->attempts->latestFor($lockedPayment)->attempt_number ?? 0) + 1;

            return $this->attempts->create($lockedPayment, [
                'attempt_number' => $nextNumber,
                'status' => PaymentAttemptStatus::Pending,
                'attempted_at' => now(),
            ]);
        });
    }

    public function attachProviderSession(Payment $payment, string $sessionId): PaymentAttempt
    {
        $attempt = $this->begin($payment);

        return $this->attempts->save($attempt, ['provider_session_id' => $sessionId]);
    }

    public function succeed(Payment $payment): void
    {
        $this->resolve($payment, PaymentAttemptStatus::Succeeded);
    }

    public function expire(Payment $payment): void
    {
        $this->resolve($payment, PaymentAttemptStatus::Expired);
    }

    public function fail(Payment $payment, \Throwable $exception): void
    {
        $this->failWithCode($payment, class_basename($exception));
    }

    public function failWithCode(Payment $payment, string $code): void
    {
        $attempt = $this->attempts->pendingFor($payment) ?? $this->begin($payment);

        $this->attempts->save($attempt, [
            'status' => PaymentAttemptStatus::Failed,
            'failure_code' => Str::limit($code, 100),
            'failure_summary' => Str::limit('Payment provider could not complete this attempt.', 500),
            'resolved_at' => now(),
        ]);
    }

    private function resolve(Payment $payment, PaymentAttemptStatus $status): void
    {
        $attempt = $this->attempts->pendingFor($payment);

        if ($attempt === null) {
            return;
        }

        $this->attempts->save($attempt, [
            'status' => $status,
            'resolved_at' => now(),
        ]);
    }
}
