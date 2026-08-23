<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $order = $checkout->checkout($request->user(), (string) $request->validated('payment_method'), [
            'recipient_name' => $request->validated('recipient_name'),
            'address_line_one' => $request->validated('address_line_one'),
            'address_line_two' => $request->validated('address_line_two'),
            'city' => $request->validated('city'),
            'postal_code' => $request->validated('postal_code'),
            'phone' => $request->validated('phone'),
        ]);

        return to_route('buyer.orders.index')->with('status', "Order {$order->number} was created. Complete payment to release it to the seller.");
    }
}
