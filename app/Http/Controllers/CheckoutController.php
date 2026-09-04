<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CartRepository;
use App\Http\Requests\CheckoutPaymentRequest;
use App\Http\Requests\CheckoutRequest;
use App\Models\CustomerOrder;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    private const array PAYMENT_METHODS = ['stripe', 'bank_transfer', 'cod'];

    public function __construct(
        private readonly CartRepository $carts,
        private readonly CheckoutService $checkout,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('buyer/checkout', [
            'cart' => $this->carts->forBuyer($request->user()),
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
            'cart' => $this->carts->forBuyer($request->user()),
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

        return Inertia::render('buyer/review', [
            'cart' => $this->carts->forBuyer($request->user()),
            'shippingAddress' => $shippingAddress,
            'paymentMethod' => $paymentMethod,
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress)) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before placing your order.']);
        }

        $paymentMethod = $this->paymentMethod($request);

        if ($paymentMethod === null) {
            return to_route('checkout.payment.show')->withErrors(['payment_method' => 'Choose a payment method before placing your order.']);
        }

        $order = $this->checkout->checkout($request->user(), $paymentMethod, $shippingAddress);
        $request->session()->forget('checkout');

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
