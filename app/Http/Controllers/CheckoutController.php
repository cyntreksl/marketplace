<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CartRepository;
use App\Http\Requests\CheckoutPaymentRequest;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly CartRepository $carts) {}

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
        ]);
    }

    public function storePayment(CheckoutPaymentRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $shippingAddress = $request->session()->get('checkout.shipping_address');

        if (! is_array($shippingAddress)) {
            return to_route('checkout.show')->withErrors(['checkout' => 'Add your delivery details before choosing a payment method.']);
        }

        $paymentMethod = (string) $request->validated('payment_method');
        $order = $checkout->checkout($request->user(), $paymentMethod, $shippingAddress);
        $request->session()->forget('checkout.shipping_address');

        $status = $paymentMethod === 'cod'
            ? "Order {$order->number} was confirmed for cash on delivery."
            : "Order {$order->number} was created. Complete payment to release it to the seller.";

        return to_route('buyer.orders.index')->with('status', $status);
    }
}
