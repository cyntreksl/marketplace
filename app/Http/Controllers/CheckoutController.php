<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CheckoutRepository;
use App\Http\Requests\CheckoutPaymentRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\CustomerOrder;
use App\Services\CartService;
use App\Services\CheckoutPaymentService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    private const array PAYMENT_METHODS = ['stripe', 'cod'];

    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutRepository $orders,
        private readonly CheckoutPaymentService $payments,
        private readonly CheckoutService $checkout,
    ) {}

    public function show(Request $request): Response
    {
        $request->session()->forget('checkout_intended');

        return Inertia::render('buyer/checkout', [
            'cart' => $this->carts->summary($request),
            'shippingAddress' => $request->session()->get('checkout.shipping_address'),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $request->session()->put('checkout.shipping_address', [
            'recipient_name' => $request->validated('recipient_name'),
            'address_line_one' => $request->validated('address_line_one'),
            'address_line_two' => $request->validated('address_line_two'),
            'city' => $request->validated('city'),
            'postal_code' => $request->validated('postal_code'),
            'phone' => $request->validated('phone'),
        ]);

        return to_route('checkout.payment.show');
    }

    public function showPayment(Request $request): Response|RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress)) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before choosing a payment method.']);
        }

        return Inertia::render('buyer/payment', [
            'cart' => $this->carts->summary($request),
            'shippingAddress' => $shippingAddress,
            'paymentMethod' => $this->paymentMethod($request),
        ]);
    }

    public function storePayment(CheckoutPaymentRequest $request): RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress)) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before choosing a payment method.']);
        }

        $paymentMethod = (string) $request->validated('payment_method');
        $request->session()->put('checkout.payment_method', $paymentMethod);

        return to_route('checkout.review.show');
    }

    public function showReview(Request $request): Response|RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress)) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before reviewing your order.']);
        }

        $paymentMethod = $this->paymentMethod($request);

        if ($paymentMethod === null) {
            return to_route('checkout.payment.show')->withErrors(['payment_method' => 'Choose a payment method before reviewing your order.']);
        }

        $cart = $this->carts->summary($request);
        $token = $request->session()->get('checkout.token', (string) Str::uuid());
        $request->session()->put('checkout.token', $token);

        return Inertia::render('buyer/review', [
            'checkoutToken' => $token,
            'reviewHash' => $this->checkout->reviewHash($cart),
            'cart' => $cart,
            'shippingAddress' => $shippingAddress,
            'paymentMethod' => $paymentMethod,
        ]);
    }

    public function placeOrder(PlaceOrderRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $existing = $this->orders->findSubmission($request->user(), $request->validated('checkout_token'));
        if ($existing !== null) {
            return to_route('checkout.thank_you.show', $existing->number);
        }
        abort_unless($request->session()->get('checkout.token') === $request->validated('checkout_token'), 419);
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress)) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before placing your order.']);
        }

        $paymentMethod = $this->paymentMethod($request);

        if ($paymentMethod === null) {
            return to_route('checkout.payment.show')->withErrors(['payment_method' => 'Choose a payment method before placing your order.']);
        }

        $order = $this->checkout->checkout($request->user(), $paymentMethod, $shippingAddress, $request->validated('checkout_token'), $request->validated('review_hash'));
        $request->session()->forget('checkout');

        if ($paymentMethod === 'stripe') {
            try {
                $url = $this->payments->start($order);
                if ($url !== null) {
                    return Inertia::location($url);
                }
            } catch (\Throwable $exception) {
                report($exception);

                return to_route('checkout.thank_you.show', $order->number)->withErrors(['payment' => 'Your order is saved. Please retry card payment.']);
            }
        }

        return to_route('checkout.thank_you.show', ['customerOrder' => $order->number]);
    }

    public function thankYou(CustomerOrder $customerOrder): Response
    {
        Gate::authorize('view', $customerOrder);

        return Inertia::render('buyer/thank-you', [
            'order' => $this->checkout->confirmationSummary($customerOrder),
        ]);
    }

    private function paymentMethod(Request $request): ?string
    {
        $paymentMethod = $request->session()->get('checkout.payment_method');

        return is_string($paymentMethod) && in_array($paymentMethod, self::PAYMENT_METHODS, true)
            ? $paymentMethod
            : null;
    }
}
