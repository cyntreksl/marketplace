<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Models\Cart;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function show(Request $request): Response
    {
        $cart = Cart::query()->firstOrCreate(['buyer_id' => $request->user()->id]);

        return Inertia::render('buyer/cart', [
            'cart' => $cart->load(['items.listing.sellerProfile']),
        ]);
    }

    public function store(AddCartItemRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $checkout->addItem($request->user(), (int) $request->validated('listing_id'), (int) $request->validated('quantity'));

        return to_route('cart.show')->with('status', 'Item added to your cart.');
    }
}
