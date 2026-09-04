<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\OrderAcknowledgmentNotification;
use Illuminate\Support\Facades\Notification;

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

test('buyer reviews and places an order before the checkout session and cart are cleared', function (): void {
    Notification::fake();

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

    $paymentResponse = $this->actingAs($user)->post(route('checkout.payment.store'), [
        'payment_method' => 'cod',
    ]);

    $paymentResponse->assertRedirect(route('checkout.review.show'));
    $paymentResponse->assertSessionHas('checkout.payment_method', 'cod');

    expect(CustomerOrder::query()->count())->toBe(0)
        ->and($cart->fresh()?->items)->toHaveCount(1);

    $this->actingAs($user)
        ->get(route('checkout.review.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/review')
            ->has('cart.items', 1)
            ->where('shippingAddress.recipient_name', 'Saman Perera')
            ->where('paymentMethod', 'cod'));

    $response = $this->actingAs($user)->post(route('checkout.review.store'));

    $order = CustomerOrder::query()->whereBelongsTo($user, 'buyer')->sole();

    $response->assertRedirect(route('checkout.thank_you.show', ['customerOrder' => $order->number]));
    $response->assertSessionMissing('checkout');

    expect($cart->fresh()?->items()->count())->toBe(0)
        ->and($order->number)->toMatch('/^PRO\d{6}$/');
    $this->assertDatabaseHas('customer_orders', [
        'buyer_id' => $user->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get(route('checkout.thank_you.show', ['customerOrder' => $order->number]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/thank-you')
            ->where('order.number', $order->number)
            ->where('order.status', 'confirmed')
            ->where('order.total', '22000.00')
            ->where('order.payment.method', 'cod')
            ->where('order.payment.status', 'pending_collection')
            ->where('order.shippingAddress.recipient_name', 'Saman Perera')
            ->where('order.shippingAddress.address_line_one', '123, Galle Road')
            ->where('order.billingAddress.recipient_name', 'Saman Perera')
            ->where('order.billingAddress.city', 'Colombo')
            ->has('order.items', 1)
            ->where('order.items.0.title', $listing->title)
            ->where('order.items.0.quantity', 1));

    Notification::assertSentTo(
        $user,
        OrderAcknowledgmentNotification::class,
        fn (OrderAcknowledgmentNotification $notification): bool => $notification->paymentMethod === 'cod'
            && $notification->itemCount === 1
            && $notification->orderTotal === '22000.00'
            && $notification->orderNumber === $order->number,
    );
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
        ->assertRedirect(route('checkout.review.show'));

    $response = $this->actingAs($user)->post(route('checkout.review.store'));

    $order = CustomerOrder::query()->whereBelongsTo($user, 'buyer')->sole();
    $payment = $order->payments()->sole();

    $response->assertRedirect(route('checkout.thank_you.show', ['customerOrder' => $order->number]));

    expect($order->status)->toBe('pending_payment')
        ->and($payment->method)->toBe($paymentMethod)
        ->and($payment->status)->toBe('pending');
})->with(['stripe', 'bank_transfer']);

test('a buyer cannot view another buyers order confirmation', function (): void {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $order = CustomerOrder::factory()->for($buyer, 'buyer')->create();

    $this->actingAs($otherBuyer)
        ->get(route('checkout.thank_you.show', ['customerOrder' => $order->number]))
        ->assertForbidden();
});

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

test('review page requires completed shipping and payment details', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.review.show'))
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHasErrors('checkout');

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
        ->get(route('checkout.review.show'))
        ->assertRedirect(route('checkout.payment.show'))
        ->assertSessionHasErrors('payment_method');
});
