<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\User;

test('buy now saves the item and redirects directly to checkout', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create([
        'listing_type' => 'buy_now',
        'status' => 'approved',
        'is_active' => true,
        'stock_quantity' => 5,
    ]);

    $response = $this->actingAs($user)->post(route('cart.items.store'), [
        'listing_id' => $listing->id,
        'quantity' => 1,
        'buy_now' => true,
    ]);

    $response->assertRedirect(route('checkout.show'));

    $cart = Cart::query()->whereBelongsTo($user, 'buyer')->sole();

    expect($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->listing_id)->toBe($listing->id);
});

test('buyer checkout page renders the saved cart summary', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user, 'buyer')->create();
    $listing = Listing::factory()->create([
        'listing_type' => 'buy_now',
        'status' => 'approved',
        'is_active' => true,
        'price' => 25000,
        'sale_price' => 22000,
    ]);

    CartItem::factory()->for($cart)->create([
        'listing_id' => $listing->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($user)->get('/checkout');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('buyer/checkout')
        ->has('cart.items', 1));
});

test('buyer checkout creates an order and clears the cart', function (): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user, 'buyer')->create();
    $listing = Listing::factory()->create([
        'listing_type' => 'buy_now',
        'status' => 'approved',
        'is_active' => true,
        'price' => 25000,
        'sale_price' => 22000,
    ]);

    CartItem::factory()->for($cart)->create([
        'listing_id' => $listing->id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->post('/checkout', [
        'payment_method' => 'cod',
        'recipient_name' => 'Saman Perera',
        'address_line_one' => '123, Galle Road',
        'address_line_two' => 'Apartment 5B',
        'city' => 'Colombo',
        'postal_code' => '00300',
        'phone' => '0771234567',
    ]);

    $response->assertRedirect(route('buyer.orders.index'));

    expect($cart->fresh()?->items()->count())->toBe(0);
    $this->assertDatabaseHas('customer_orders', [
        'buyer_id' => $user->id,
        'status' => 'confirmed',
    ]);
});
