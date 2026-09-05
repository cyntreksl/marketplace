<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Start a payment with the configured gateway.
     *
     * @return array{reference: string, redirect_url: string|null, expires_at: int}
     */
    public function createPayment(Payment $payment): array;

    /**
     * Verify and normalize an incoming provider callback.
     *
     * @return array<string, mixed>
     */
    public function verifyCallback(string $payload, ?string $signature): array;

    /** @return array<string, mixed> */
    public function retrieveCheckout(string $reference): array;

    /** @return array<string, mixed> */
    public function expireCheckout(string $reference): array;

    /**
     * Request a refund for a captured payment.
     *
     * @return array{reference: string, status: string}
     */
    public function refund(Payment $payment, string $amount, string $idempotencyKey): array;
}
