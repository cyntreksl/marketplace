<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerOrder;
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

test('buyer shipping details continue to the payment page', function (): void {
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

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'recipient_name' => 'Saman Perera',
        'address_line_one' => '123, Galle Road',
        'address_line_two' => 'Apartment 5B',
        'city' => 'Colombo',
        'postal_code' => '00300',
        'phone' => '0771234567',
    ]);

    $response->assertRedirect(route('checkout.payment.show'));
    $response->assertSessionHas('checkout.shipping_address.city', 'Colombo');
    expect(CustomerOrder::query()->count())->toBe(0)
        ->and($cart->fresh()?->items)->toHaveCount(1);

    $this->actingAs($user)
        ->get(route('checkout.payment.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/payment')
            ->has('cart.items', 1)
            ->where('shippingAddress.recipient_name', 'Saman Perera')
            ->where('shippingAddress.city', 'Colombo'));
});

test('buyer payment creates an order and clears the checkout session and cart', function (): void {
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

    $this->actingAs($user)->post(route('checkout.store'), [
        'recipient_name' => 'Saman Perera',
        'address_line_one' => '123, Galle Road',
        'address_line_two' => 'Apartment 5B',
        'city' => 'Colombo',
        'postal_code' => '00300',
        'phone' => '0771234567',
    ])->assertRedirect(route('checkout.payment.show'));

    $response = $this->actingAs($user)->post(route('checkout.payment.store'), [
        'payment_method' => 'cod',
    ]);

    $response->assertRedirect(route('buyer.orders.index'));
    $response->assertSessionMissing('checkout.shipping_address');

    expect($cart->fresh()?->items()->count())->toBe(0);
    $this->assertDatabaseHas('customer_orders', [
        'buyer_id' => $user->id,
        'status' => 'confirmed',
    ]);
});

test('buyer can create a pending order with an online payment method', function (string $paymentMethod): void {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user, 'buyer')->create();
    $listing = Listing::factory()->create([
        'listing_type' => 'buy_now',
        'status' => 'approved',
        'is_active' => true,
        'price' => 12500,
    ]);

    CartItem::factory()->for($cart)->create([
        'listing_id' => $listing->id,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->withSession([
            'checkout.shipping_address' => [
                'recipient_name' => 'Saman Perera',
                'address_line_one' => '123, Galle Road',
                'address_line_two' => null,
                'city' => 'Colombo',
                'postal_code' => '00300',
                'phone' => '0771234567',
            ],
        ])
        ->post(route('checkout.payment.store'), [
            'payment_method' => $paymentMethod,
        ])
        ->assertRedirect(route('buyer.orders.index'));

    $order = CustomerOrder::query()->whereBelongsTo($user, 'buyer')->sole();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe('pending_payment')
        ->and($payment->method)->toBe($paymentMethod)
        ->and($payment->status)->toBe('pending');
})->with(['stripe', 'bank_transfer']);

test('payment methods that are not available are rejected', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'checkout.shipping_address' => [
                'recipient_name' => 'Saman Perera',
                'address_line_one' => '123, Galle Road',
                'address_line_two' => null,
                'city' => 'Colombo',
                'postal_code' => null,
                'phone' => '0771234567',
            ],
        ])
        ->post(route('checkout.payment.store'), [
            'payment_method' => 'mobile_wallet',
        ])
        ->assertSessionHasErrors('payment_method');
});

test('payment page requires completed shipping details', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.payment.show'))
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHasErrors('checkout');

    $this->actingAs($user)
        ->post(route('checkout.payment.store'), ['payment_method' => 'cod'])
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHasErrors('checkout');
});
