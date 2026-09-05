<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\Repositories\CheckoutRepository;
use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Notifications\PaymentConfirmedNotification;
use Brick\Math\BigDecimal;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutPaymentService
{
    public function __construct(private readonly CheckoutRepository $orders, private readonly PaymentGateway $gateway) {}

    public function start(CustomerOrder $order): ?string
    {
        $payment = $this->orders->payment($order);

        return Cache::lock('checkout-payment-'.$payment->id, 30)->block(5, function () use ($payment): ?string {
            $payment = $this->orders->findPayment($payment->id);
            if ($payment === null || $payment->status !== 'pending') {
                return null;
            }
            if ($payment->checkout_session_id !== null) {
                $this->reconcile($payment);
                $payment = $this->orders->findPayment($payment->id);

                return $payment?->status === 'pending' ? $payment->checkout_url : null;
            }
            if ($payment->expires_at === null) {
                throw ValidationException::withMessages(['payment' => 'This older order needs support assistance to complete payment.']);
            }
            $session = $this->gateway->createPayment($payment);
            DB::transaction(function () use ($payment, $session): void {
                $locked = $this->orders->lockPayment($payment->id);
                $this->orders->savePayment($locked, ['checkout_session_id' => $session['reference'], 'checkout_url' => $session['redirect_url']]);
            });

            if ($payment->expires_at->isPast()) {
                $this->reconcile($this->orders->payment($payment->customerOrder));

                return null;
            }

            return $session['redirect_url'];
        });
    }

    public function refresh(CustomerOrder $order): void
    {
        $this->reconcile($this->orders->payment($order));
    }

    public function webhook(string $body, ?string $signature): void
    {
        $event = $this->gateway->verifyCallback($body, $signature);
        if (! in_array($event['type'], ['checkout.session.completed', 'checkout.session.expired', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed'], true)) {
            return;
        }
        $session = $event['data']['object'];
        $payment = $this->orders->findPayment((int) data_get($session, 'metadata.payment_id', 0));
        if ($payment === null || $payment->method !== 'stripe') {
            return;
        }
        if ($payment->checkout_session_id !== null && $payment->checkout_session_id !== $session['id']) {
            abort(400, 'Payment session mismatch.');
        }
        $this->apply($payment->id, $this->gateway->retrieveCheckout($session['id']));
    }

    public function reconcile(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }
        if ($payment->checkout_session_id === null) {
            $this->start($payment->customerOrder);
            $payment = $this->orders->findPayment($payment->id);
        }
        if ($payment?->checkout_session_id !== null && $payment->status === 'pending') {
            $session = $this->gateway->retrieveCheckout($payment->checkout_session_id);
            if ($payment->expires_at?->isPast() && ($session['status'] ?? '') === 'open') {
                try {
                    $session = $this->gateway->expireCheckout($payment->checkout_session_id);
                } catch (RequestException $exception) {
                    if ($exception->response->status() !== 400) {
                        throw $exception;
                    }
                    $session = $this->gateway->retrieveCheckout($payment->checkout_session_id);
                }
            }
            $this->apply($payment->id, $session);
        }
    }

    /** @param array<string, mixed> $session */
    private function apply(int $paymentId, array $session): void
    {
        DB::transaction(function () use ($paymentId, $session): void {
            $payment = $this->orders->lockPayment($paymentId);
            abort_unless(
                (string) data_get($session, 'metadata.payment_id') === (string) $payment->id
                && (string) ($session['client_reference_id'] ?? '') === (string) $payment->customer_order_id
                && ($session['currency'] ?? '') === 'lkr'
                && BigDecimal::of((string) ($session['amount_total'] ?? -1))->isEqualTo(BigDecimal::of($payment->amount)->multipliedBy(100))
                && ($payment->checkout_session_id === null || $payment->checkout_session_id === $session['id']),
                400, 'Payment details mismatch.',
            );
            if ($payment->status !== 'pending') {
                return;
            }
            if (($session['payment_status'] ?? '') === 'paid' && ($session['status'] ?? '') === 'complete') {
                abort_unless(is_string($session['payment_intent'] ?? null), 400);
                $this->orders->savePayment($payment, ['status' => 'paid', 'paid_at' => now(), 'provider_reference' => $session['payment_intent'], 'checkout_session_id' => $session['id']]);
                $this->orders->confirm($payment->customerOrder);
                $payment->customerOrder->buyer->notify(new PaymentConfirmedNotification($payment->customerOrder->number, $payment->amount));
            } elseif (($session['status'] ?? '') === 'expired' && ($session['payment_status'] ?? '') === 'unpaid') {
                $this->expire($payment->id);
            }
        });
    }

    private function expire(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId): void {
            $payment = $this->orders->lockPayment($paymentId);
            if ($payment->status === 'pending') {
                $this->orders->savePayment($payment, ['status' => 'expired']);
                $this->orders->expire($payment->customerOrder);
            }
        });
    }
}
