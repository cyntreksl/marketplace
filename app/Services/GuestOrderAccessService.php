<?php

namespace App\Services;

use App\Models\CustomerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class GuestOrderAccessService
{
    private const string CHECKOUT_IDENTITY_KEY = 'checkout.guest_identity';

    private const string CHECKOUT_ACCESS_KEY = 'checkout.guest_access_token';

    private const string ORDER_ACCESS_KEY = 'guest_order_access';

    private const string COMPLETED_IDENTITY_KEY = 'completed_checkout_identity';

    private const string COMPLETED_ACCESS_KEY = 'completed_checkout_access';

    public function identityHash(Request $request): ?string
    {
        if ($request->user() !== null) {
            return null;
        }

        $identity = $request->session()->get(self::CHECKOUT_IDENTITY_KEY);

        if (! is_string($identity) || $identity === '') {
            $identity = $request->session()->get('guest_cart_token');
        }

        if (! is_string($identity) || $identity === '') {
            $identity = Str::random(64);
        }

        $request->session()->put(self::CHECKOUT_IDENTITY_KEY, $identity);

        return hash('sha256', $identity);
    }

    public function checkoutAccessToken(Request $request): ?string
    {
        if ($request->user() !== null) {
            return null;
        }

        $token = $request->session()->get(self::CHECKOUT_ACCESS_KEY);
        if (! is_string($token) || $token === '') {
            $token = Str::random(64);
            $request->session()->put(self::CHECKOUT_ACCESS_KEY, $token);
        }

        return $token;
    }

    public function submissionIdentityHash(Request $request, string $checkoutToken): ?string
    {
        if ($request->user() !== null) {
            return null;
        }

        $completed = $request->session()->get(self::COMPLETED_IDENTITY_KEY.'.'.$checkoutToken);

        return is_string($completed) && $completed !== ''
            ? $completed
            : $this->identityHash($request);
    }

    public function submissionAccessToken(Request $request, string $checkoutToken): ?string
    {
        if ($request->user() !== null) {
            return null;
        }

        $completed = $request->session()->get(self::COMPLETED_ACCESS_KEY.'.'.$checkoutToken);

        return is_string($completed) && $completed !== ''
            ? $completed
            : $this->checkoutAccessToken($request);
    }

    public function rememberSubmission(Request $request, string $checkoutToken, ?string $identityHash, ?string $accessToken): void
    {
        if ($identityHash !== null) {
            $request->session()->put(self::COMPLETED_IDENTITY_KEY.'.'.$checkoutToken, $identityHash);
        }

        if ($accessToken !== null) {
            $request->session()->put(self::COMPLETED_ACCESS_KEY.'.'.$checkoutToken, $accessToken);
        }
    }

    public function remember(Request $request, CustomerOrder $order, ?string $token): void
    {
        if ($token !== null && $order->buyer_id === null) {
            $request->session()->put(self::ORDER_ACCESS_KEY.'.'.$order->id, $token);
        }
    }

    public function token(Request $request, CustomerOrder $order): ?string
    {
        $queryToken = $request->query('access');

        if (is_string($queryToken) && $queryToken !== '') {
            return $queryToken;
        }

        $sessionToken = $request->session()->get(self::ORDER_ACCESS_KEY.'.'.$order->id);

        return is_string($sessionToken) && $sessionToken !== '' ? $sessionToken : null;
    }

    public function authorize(Request $request, CustomerOrder $order): void
    {
        if ($request->user() !== null && $order->buyer_id === $request->user()->id) {
            return;
        }

        abort_unless($order->buyer_id === null && $this->hasValidToken($request, $order), 403);
    }

    public function authorizePayment(Request $request, CustomerOrder $order): void
    {
        $this->authorize($request, $order);

        if ($order->buyer_id !== null) {
            return;
        }

        $payment = $order->payments()->where('method', 'stripe')->first();
        abort_unless($payment !== null && ($payment->status === 'paid' || $payment->expires_at?->isFuture()), 410);
    }

    public function confirmationUrl(CustomerOrder $order, ?string $token): string
    {
        return route('checkout.thank_you.show', array_filter([
            'customerOrder' => $order->number,
            'access' => $token,
        ]));
    }

    public function claimUrl(CustomerOrder $order): string
    {
        return URL::temporarySignedRoute(
            'guest-orders.claim',
            now()->addDays(7),
            ['customerOrder' => $order->number],
        );
    }

    private function hasValidToken(Request $request, CustomerOrder $order): bool
    {
        $token = $this->token($request, $order);

        return $token !== null
            && is_string($order->guest_access_token_hash)
            && hash_equals($order->guest_access_token_hash, hash('sha256', $token));
    }
}
