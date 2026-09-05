<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): Response
    {
        return Inertia::render('buyer/cart', ['cart' => $this->carts->summary($request)]);
    }

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $this->carts->add($request, (int) $request->validated('listing_id'), $request->validated('listing_variant_id') === null ? null : (int) $request->validated('listing_variant_id'), (int) $request->validated('quantity'));
        if ($request->validated('buy_now', false)) {
            return to_route('checkout.show');
        }

        return back()->with('cart_added', true)->with('toast', ['type' => 'success', 'message' => 'Added to cart.']);
    }

    public function update(UpdateCartItemRequest $request, string $item): RedirectResponse
    {
        $this->carts->update($request, $item, (int) $request->validated('quantity'));

        return back();
    }

    public function destroy(Request $request, string $item): RedirectResponse
    {
        $this->carts->update($request, $item, null);

        return back();
    }
}
