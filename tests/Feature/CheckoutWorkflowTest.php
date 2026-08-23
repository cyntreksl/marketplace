<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;

test('a buyer checkout splits one cart into seller fulfilment orders', function () {
    $buyer = User::factory()->create();
    $category = Category::factory()->create();
    $firstSeller = SellerProfile::factory()->create();
    $secondSeller = SellerProfile::factory()->create();
    $firstListing = Listing::factory()->create(['seller_profile_id' => $firstSeller->id, 'category_id' => $category->id, 'price' => 10000, 'stock_quantity' => 3]);
    $secondListing = Listing::factory()->create(['seller_profile_id' => $secondSeller->id, 'category_id' => $category->id, 'price' => 25000, 'stock_quantity' => 2]);

    $this->actingAs($buyer)->post(route('cart.items.store'), ['listing_id' => $firstListing->id, 'quantity' => 2])->assertRedirect();
    $this->actingAs($buyer)->post(route('cart.items.store'), ['listing_id' => $secondListing->id, 'quantity' => 1])->assertRedirect();

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'payment_method' => 'cod',
        'recipient_name' => 'Buyer Name',
        'address_line_one' => '10 Galle Road',
        'city' => 'Colombo',
        'phone' => '0771234567',
    ])->assertRedirect(route('buyer.orders.index', absolute: false));

    $order = $buyer->fresh()->cart()->firstOrFail();

    expect(Cart::query()->where('buyer_id', $buyer->id)->firstOrFail()->items)->toHaveCount(0)
        ->and($buyer->fresh()->cart)->not->toBeNull()
        ->and(CustomerOrder::query()->where('buyer_id', $buyer->id)->sole()->sellerOrders)->toHaveCount(2)
        ->and($firstListing->refresh()->reserved_quantity)->toBe(2)
        ->and($secondListing->refresh()->reserved_quantity)->toBe(1);
});

test('cash on delivery is unavailable when the cart total exceeds the configured limit', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 60000]);
    $cart = Cart::factory()->create(['buyer_id' => $buyer->id]);
    $cart->items()->create(['listing_id' => $listing->id, 'quantity' => 1]);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'payment_method' => 'cod',
        'recipient_name' => 'Buyer Name',
        'address_line_one' => '10 Galle Road',
        'city' => 'Colombo',
        'phone' => '0771234567',
    ])->assertSessionHasErrors('payment_method');
});
