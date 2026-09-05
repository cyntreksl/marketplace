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
            ->withHeader('Idempotency-Key', $payment->idempotency_key)
            ->connectTimeout(3)->timeout(10)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'lkr',
                        'unit_amount' => BigDecimal::of($payment->amount)->multipliedBy(100)->toInt(),
                        'product_data' => ['name' => 'Order '.$payment->customerOrder->number.' (including delivery)'],
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => (string) $payment->customer_order_id,
                'metadata' => ['payment_id' => (string) $payment->id],
                'payment_intent_data' => ['metadata' => ['payment_id' => (string) $payment->id]],
                'success_url' => route('checkout.card.return', $payment->customerOrder->number),
                'cancel_url' => route('checkout.thank_you.show', $payment->customerOrder->number),
            ])->throw()->json();

        return ['reference' => $response['id'], 'redirect_url' => $response['url'], 'expires_at' => $response['expires_at']];
    }

    public function retrieveCheckout(string $reference): array
    {
        return Http::withBasicAuth((string) config('services.stripe.secret'), '')
            ->connectTimeout(3)->timeout(10)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($reference))
            ->throw()->json();
    }

    public function expireCheckout(string $reference): array
    {
        return Http::asForm()->withBasicAuth((string) config('services.stripe.secret'), '')
            ->connectTimeout(3)->timeout(10)
            ->post('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($reference).'/expire')
            ->throw()->json();
    }

    public function verifyCallback(string $payload, ?string $signature): array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $parts = explode(',', (string) $signature);
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        abort_unless($secret !== '' && $timestamp !== null && abs(time() - $timestamp) <= 300, 400);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        abort_unless(array_any($signatures, fn (string $value): bool => hash_equals($expected, $value)), 400);
        try {
            $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400, 'Invalid event body.');
        }
        abort_unless(is_array($event) && isset($event['id'], $event['type'], $event['data']['object']), 400);

        return $event;
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
