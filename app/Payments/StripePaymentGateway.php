<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Http;

class StripePaymentGateway implements PaymentGateway
{
    public function createPayment(Payment $payment): array
    {
        $response = Http::asForm()
            ->withBasicAuth((string) config('services.stripe.secret'), '')
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(2, 200)
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => BigDecimal::of($payment->amount)->multipliedBy(100)->toScale(0, RoundingMode::Down)->toInt(),
                'currency' => 'lkr',
                'metadata[customer_order_id]' => $payment->customer_order_id,
                'metadata[payment_id]' => $payment->id,
            ])->throw()->json();

        return ['reference' => $response['id'], 'redirect_url' => $response['next_action']['redirect_to_url']['url'] ?? null];
    }

    public function verifyCallback(array $payload, ?string $signature): array
    {
        abort_unless(hash_equals((string) config('services.stripe.webhook_secret'), (string) $signature), 403);

        $object = data_get($payload, 'data.object', []);

        return [
            'reference' => (string) data_get($object, 'id'),
            'status' => data_get($payload, 'type') === 'payment_intent.succeeded' ? 'paid' : 'failed',
            'amount' => (string) BigDecimal::of((string) data_get($object, 'amount', 0))->dividedBy(100, 2, RoundingMode::Down),
        ];
    }

    public function refund(Payment $payment, string $amount, string $idempotencyKey): array
    {
        $response = Http::asForm()
            ->withBasicAuth((string) config('services.stripe.secret'), '')
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(2, 200)
            ->post('https://api.stripe.com/v1/refunds', [
                'payment_intent' => $payment->provider_reference,
                'amount' => BigDecimal::of($amount)->multipliedBy(100)->toScale(0, RoundingMode::Down)->toInt(),
            ])->throw()->json();

        return [
            'reference' => (string) $response['id'],
            'status' => match ($response['status'] ?? null) {
                'succeeded' => 'succeeded',
                'failed', 'canceled' => 'failed',
                default => 'pending',
            },
        ];
    }
}
