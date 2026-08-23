<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Start a payment with the configured gateway.
     *
     * @return array{reference: string, redirect_url: string|null}
     */
    public function createPayment(Payment $payment): array;

    /**
     * Verify and normalize an incoming provider callback.
     *
     * @param  array<string, mixed>  $payload
     * @return array{reference: string, status: string, amount: string}
     */
    public function verifyCallback(array $payload, ?string $signature): array;

    /**
     * Request a refund for a captured payment.
     */
    public function refund(Payment $payment, string $amount): void;
}
