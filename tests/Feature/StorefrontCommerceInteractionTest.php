<?php

use App\Models\Cart;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\ListingVariantOption;
use App\Models\ListingVariantOptionValue;
use App\Models\ProductQuestion;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Watchlist;

test('wishlist actions require authentication and are idempotent', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->post(route('wishlist.store', $listing->slug))->assertRedirect(route('login'));

    $this->actingAs($buyer)->post(route('wishlist.store', $listing->slug))->assertRedirect();
    $this->actingAs($buyer)->post(route('wishlist.store', $listing->slug))->assertRedirect();

    expect(Watchlist::query()->whereBelongsTo($buyer, 'buyer')->whereBelongsTo($listing)->count())->toBe(1);

    $this->actingAs($buyer)->delete(route('wishlist.destroy', $listing->slug))->assertRedirect();

    expect(Watchlist::query()->whereBelongsTo($buyer, 'buyer')->whereBelongsTo($listing)->count())->toBe(0);
});

test('comparison accepts up to four identifiers and excludes unavailable listings', function () {
    $publicListing = Listing::factory()->create();
    $hiddenListing = Listing::factory()->create(['status' => 'draft', 'approved_at' => null]);

    $this->getJson(route('compare.listings', ['ids' => [$publicListing->id, $hiddenListing->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'listings')
        ->assertJsonPath('listings.0.id', $publicListing->id);

    $this->getJson(route('compare.listings', ['ids' => Listing::factory()->count(5)->create()->modelKeys()]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids');
});

test('buyers can ask questions and only answered questions are public', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create();
    $seller = $listing->sellerProfile->user;

    $this->actingAs($buyer)->post(route('listings.questions.store', $listing->slug), [
        'question' => 'Does this include the original charger?',
    ])->assertRedirect();

    $question = ProductQuestion::query()->sole();

    auth()->logout();
    $this->get(route('listings.show', $listing->slug))
        ->assertInertia(fn ($page) => $page
            ->has('questions', 0)
            ->has('pendingQuestions', 0));

    $this->actingAs($buyer)->get(route('listings.show', $listing->slug))
        ->assertInertia(fn ($page) => $page
            ->has('questions', 0)
            ->has('pendingQuestions', 1)
            ->where('pendingQuestions.0.question', 'Does this include the original charger?'));

    $this->actingAs($seller)->patch(route('product-questions.update', $question), [
        'answer' => 'Yes, the original charger is included.',
    ])->assertRedirect();

    $this->get(route('listings.show', $listing->slug))
        ->assertInertia(fn ($page) => $page
            ->has('questions', 1)
            ->where('questions.0.answer', 'Yes, the original charger is included.'));
});

test('a different seller cannot answer a product question', function () {
    $question = ProductQuestion::factory()->create();
    $otherSeller = Listing::factory()->create()->sellerProfile->user;

    $this->actingAs($otherSeller)->patch(route('product-questions.update', $question), [
        'answer' => 'An answer from the wrong seller.',
    ])->assertForbidden();
});

test('public tracking returns shipment progress without private order data', function () {
    $buyer = User::factory()->create(['email' => 'buyer@example.com']);
    $order = CustomerOrder::factory()->create([
        'buyer_id' => $buyer->id,
        'shipping_address' => ['name' => 'Private Buyer', 'line_1' => 'Secret address'],
    ]);
    $sellerOrder = SellerOrder::factory()->create(['customer_order_id' => $order->id]);
    Shipment::factory()->create([
        'seller_order_id' => $sellerOrder->id,
        'courier_name' => 'Island Courier',
        'tracking_number' => 'TRACK-100',
        'status_history' => [['status' => 'picked_up', 'at' => now()->toIso8601String()]],
    ]);

    $response = $this->postJson(route('order-tracking.store'), [
        'number' => strtolower($order->number),
        'email' => 'BUYER@example.com',
    ])->assertOk()
        ->assertJsonPath('order.number', $order->number)
        ->assertJsonPath('order.shipments.0.courier', 'Island Courier')
        ->assertJsonPath('order.shipments.0.trackingNumber', 'TRACK-100');

    expect($response->json('order'))->not->toHaveKeys(['shipping_address', 'buyer_id', 'payment']);

    $this->postJson(route('order-tracking.store'), [
        'number' => $order->number,
        'email' => 'wrong@example.com',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.order.0', 'We could not find an order matching those details.');
});

test('multiple variants of one listing coexist in a cart and are snapshotted at checkout', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create([
        'product_type' => 'variant',
        'stock_quantity' => 8,
        'price' => 10000,
    ]);
    $option = ListingVariantOption::factory()->create([
        'listing_id' => $listing->id,
        'name' => 'Colour',
        'position' => 0,
    ]);
    $blackValue = ListingVariantOptionValue::factory()->create([
        'listing_variant_option_id' => $option->id,
        'value' => 'Black',
        'position' => 0,
    ]);
    $silverValue = ListingVariantOptionValue::factory()->create([
        'listing_variant_option_id' => $option->id,
        'value' => 'Silver',
        'position' => 1,
    ]);
    $blackVariant = ListingVariant::factory()->create([
        'listing_id' => $listing->id,
        'seller_profile_id' => $listing->seller_profile_id,
        'combination_key' => 'colour:black',
        'sku' => 'PHONE-BLACK',
        'stock_quantity' => 3,
    ]);
    $silverVariant = ListingVariant::factory()->create([
        'listing_id' => $listing->id,
        'seller_profile_id' => $listing->seller_profile_id,
        'combination_key' => 'colour:silver',
        'sku' => 'PHONE-SILVER',
        'stock_quantity' => 4,
        'position' => 1,
    ]);
    $blackVariant->optionValues()->attach($blackValue);
    $silverVariant->optionValues()->attach($silverValue);

    $this->actingAs($buyer)->post(route('cart.items.store'), [
        'listing_id' => $listing->id,
        'listing_variant_id' => $blackVariant->id,
        'quantity' => 2,
    ])->assertRedirect();
    $this->actingAs($buyer)->post(route('cart.items.store'), [
        'listing_id' => $listing->id,
        'listing_variant_id' => $silverVariant->id,
        'quantity' => 1,
    ])->assertRedirect();

    expect(Cart::query()->whereBelongsTo($buyer, 'buyer')->sole()->items)->toHaveCount(2);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'payment_method' => 'cod',
        'recipient_name' => 'Buyer Name',
        'address_line_one' => '10 Galle Road',
        'city' => 'Colombo',
        'phone' => '0771234567',
    ])->assertRedirect(route('buyer.orders.index', absolute: false));

    $items = CustomerOrder::query()->whereBelongsTo($buyer, 'buyer')->sole()->sellerOrders()->firstOrFail()->items()->orderBy('variant_sku')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->variant_sku)->toBe('PHONE-BLACK')
        ->and($items[0]->variant_options)->toBe(['Colour' => 'Black'])
        ->and($items[1]->variant_sku)->toBe('PHONE-SILVER')
        ->and($blackVariant->refresh()->reserved_quantity)->toBe(2)
        ->and($silverVariant->refresh()->reserved_quantity)->toBe(1)
        ->and($listing->refresh()->reserved_quantity)->toBe(3);
});

test('variant products require an in-stock selection', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['product_type' => 'variant']);
    $variant = ListingVariant::factory()->create([
        'listing_id' => $listing->id,
        'seller_profile_id' => $listing->seller_profile_id,
        'stock_quantity' => 1,
        'reserved_quantity' => 1,
    ]);

    $this->actingAs($buyer)->post(route('cart.items.store'), [
        'listing_id' => $listing->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('listing_variant_id');

    $this->actingAs($buyer)->post(route('cart.items.store'), [
        'listing_id' => $listing->id,
        'listing_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('quantity');
});

test('shared storefront props expose authenticated cart quantity and wishlist count', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create();
    Watchlist::factory()->create(['buyer_id' => $buyer->id, 'listing_id' => $listing->id]);
    $cart = Cart::factory()->create(['buyer_id' => $buyer->id]);
    $cart->items()->create(['listing_id' => $listing->id, 'quantity' => 3]);

    $this->actingAs($buyer)->get(route('home'))->assertInertia(fn ($page) => $page
        ->where('commerce.cart_quantity', 3)
        ->where('commerce.wishlist_count', 1)
        ->where('marketplace.storefront.currency', 'LKR')
        ->where('marketplace.storefront.newsletter_url', null));
});
