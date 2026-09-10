<?php

namespace App\Services;

use App\Contracts\Repositories\RefundRepository;
use App\Models\Payment;
use Brick\Math\BigDecimal;

class PaymentRefundStatusService
{
    public function __construct(private readonly RefundRepository $refunds) {}

    public function recalculate(Payment $payment): Payment
    {
        $payment = $this->refunds->lockPayment($payment->id);
        $refunded = BigDecimal::of($this->refunds->successfulAmount($payment));
        $paid = BigDecimal::of($payment->amount);

        $payment->forceFill([
            'status' => $refunded->isGreaterThanOrEqualTo($paid) ? 'refunded' : 'partially_refunded',
        ]);

        return $this->refunds->savePayment($payment);
    }
}
