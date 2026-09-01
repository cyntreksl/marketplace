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
            'cart' => $cart->load(['items.listing.sellerProfile', 'items.variant.optionValues.option']),
        ]);
    }

    public function store(AddCartItemRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $checkout->addItem(
            $request->user(),
            (int) $request->validated('listing_id'),
            (int) $request->validated('quantity'),
            $request->validated('listing_variant_id') === null ? null : (int) $request->validated('listing_variant_id'),
        );

        if ($request->boolean('buy_now')) {
            return to_route('cart.show')->with('toast', ['type' => 'success', 'message' => 'Ready for checkout.']);
        }

        return back()
            ->with('status', 'Item added to your cart.')
            ->with('toast', ['type' => 'success', 'message' => 'Added to cart.']);
    }
}
