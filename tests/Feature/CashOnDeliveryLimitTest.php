<?php

use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Services\CartService;
use App\Services\CashOnDeliveryService;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function codCart(string $price): array
{
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['price' => $price, 'sale_price' => null, 'stock_quantity' => 3]);
    $cart = Cart::factory()->create(['buyer_id' => $buyer->id]);
    $cart->items()->create(['listing_id' => $listing->id, 'quantity' => 1]);

    return [$buyer, $listing, $cart];
}

test('COD eligibility includes delivery and uses an inclusive decimal limit', function (string $subtotal, string $total, bool $allowed) {
    Notification::fake();
    [$buyer, $listing, $cart] = codCart($subtotal);
    $summary = app(CartService::class)->summarize($cart->items->toArray());
    expect($summary['total'])->toBe($total)
        ->and(in_array('cod', $summary['paymentMethods'], true))->toBe($allowed);

    if ($allowed) {
        $order = app(CheckoutService::class)->checkout($buyer, 'cod', []);
        expect($order->total)->toBe($total)->and($order->status)->toBe('confirmed');
    } else {
        expect(fn () => app(CheckoutService::class)->checkout($buyer, 'cod', []))->toThrow(ValidationException::class);
        expect(CustomerOrder::count())->toBe(0)->and($listing->refresh()->reserved_quantity)->toBe(0)
            ->and($cart->items()->count())->toBe(1);
    }
})->with([
    ['4399.99', '4999.99', true],
    ['4400.00', '5000.00', true],
    ['4400.01', '5000.01', false],
    ['5000.00', '5600.00', false],
]);

test('a legacy high COD setting cannot allow totals above 5000', function () {
    MarketplaceSetting::query()->updateOrCreate(['key' => 'checkout.cod_maximum_amount'], ['group' => 'checkout', 'value' => 50000]);
    expect(app(CashOnDeliveryService::class)->allows('5000.01'))->toBeFalse();
});

test('invalid COD selection and stale review are rejected before order creation', function () {
    [$buyer, $listing] = codCart('4400.01');
    $this->actingAs($buyer)->withSession(['checkout.shipping_address' => ['city' => 'Colombo']])
        ->post(route('checkout.payment.store'), ['payment_method' => 'cod'])
        ->assertSessionHasErrors('payment_method');
    $this->withSession(['checkout.payment_method' => 'cod'])
        ->get(route('checkout.review.show'))
        ->assertRedirect(route('checkout.payment.show'))
        ->assertSessionMissing('checkout.payment_method');
    expect(CustomerOrder::count())->toBe(0)->and($listing->refresh()->reserved_quantity)->toBe(0);
});

test('final checkout rejects COD when a price increases after review', function () {
    [$buyer, $listing, $cart] = codCart('4400.00');
    $service = app(CheckoutService::class);
    $reviewHash = $service->reviewHash(app(CartService::class)->summarize($cart->items->toArray()));
    $listing->update(['price' => '4400.01']);
    expect(fn () => $service->checkout($buyer, 'cod', [], reviewHash: $reviewHash))->toThrow(ValidationException::class);
    expect(CustomerOrder::count())->toBe(0)->and($listing->refresh()->reserved_quantity)->toBe(0);
});

test('final locked pricing still enforces COD when the initial summary was eligible', function () {
    [$buyer, $listing, $cart] = codCart('4400.00');
    $summary = app(CartService::class)->summarize($cart->items->toArray());
    $this->mock(CartService::class)->shouldReceive('summarize')->once()->andReturn($summary);
    $listing->update(['price' => '4400.01']);
    expect(fn () => app(CheckoutService::class)->checkout($buyer, 'cod', []))->toThrow(ValidationException::class);
    expect(CustomerOrder::count())->toBe(0)->and($listing->refresh()->reserved_quantity)->toBe(0);
});
