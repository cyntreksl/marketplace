<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\OrderAcknowledgmentNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('seller wholesale publishing validates channels minimums and actual retail price', function (): void {
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $payload = fn (string $sku): array => [
        'category_id' => $category->id,
        'brand_name' => 'Bulk Supply Co',
        'sku' => $sku,
        'title' => 'Wholesale carton '.$sku,
        'description' => 'A complete carton intended for wholesale purchasing.',
        'condition' => 'new',
        'product_type' => 'simple',
        'stock_quantity' => 1000,
        'low_stock_threshold' => 10,
        'allow_backorders' => false,
        'is_active' => true,
        'is_featured' => false,
        'is_best_seller' => false,
        'is_new_arrival' => false,
        'images' => [UploadedFile::fake()->image('product.jpg', 1200, 1200)],
        'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1200]],
        'submit_for_review' => true,
    ];

    $this->actingAs($seller->user)->post(route('seller.listings.store'), [
        ...$payload('CHANNEL-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => false,
    ])->assertSessionHasErrors('is_retail_enabled');

    $this->post(route('seller.listings.store'), [
        ...$payload('MOQ-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'wholesale_price' => 50,
        'wholesale_min_quantity' => 1,
    ])->assertSessionHasErrors('wholesale_min_quantity');

    $this->post(route('seller.listings.store'), [
        ...$payload('PRICE-1'),
        'is_retail_enabled' => true,
        'is_wholesale_enabled' => true,
        'selling_price' => 100,
        'compare_price' => 150,
        'wholesale_price' => 100,
        'wholesale_min_quantity' => 2,
    ])->assertSessionHasErrors('wholesale_price');

    $this->post(route('seller.listings.store'), [
        ...$payload('VALID-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'wholesale_price' => 50,
        'wholesale_min_quantity' => 2,
    ])->assertSessionHasNoErrors();

    expect(Listing::query()->sole())->status->toBe('pending_review')
        ->price->toBeNull()
        ->wholesale_price->toBe('50.00')
        ->wholesale_min_quantity->toBe(2);
});

test('wholesale catalog includes wholesale inventory and retail catalog excludes wholesale only inventory', function (): void {
    $wholesaleOnly = Listing::factory()->create([
        'title' => 'Bulk paper cups', 'is_retail_enabled' => false, 'is_wholesale_enabled' => true,
        'price' => null, 'sale_price' => null, 'wholesale_price' => 12,
        'wholesale_min_quantity' => 100, 'stock_quantity' => 1000,
    ]);
    $retailOnly = Listing::factory()->create([
        'title' => 'Single coffee mug', 'is_retail_enabled' => true, 'is_wholesale_enabled' => false,
    ]);

    $this->get(route('wholesale.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('storefront/listings/index')->where('catalogMode', 'wholesale')
        ->where('listings.data.0.id', $wholesaleOnly->id)
        ->where('listings.data.0.effectivePrice', '12.00')
        ->where('listings.data.0.wholesaleMinimumQuantity', 100)->has('listings.data', 1));

    $this->get(route('listings.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('listings.data.0.id', $retailOnly->id)->has('listings.data', 1));
});

test('seller retail and wholesale areas scope products by sales channel', function (): void {
    $seller = SellerProfile::factory()->create();
    $retail = Listing::factory()->create(['seller_profile_id' => $seller->id, 'is_retail_enabled' => true, 'is_wholesale_enabled' => false]);
    $wholesale = Listing::factory()->create(['seller_profile_id' => $seller->id, 'is_retail_enabled' => false, 'is_wholesale_enabled' => true, 'wholesale_price' => 50, 'wholesale_min_quantity' => 10]);
    $both = Listing::factory()->create(['seller_profile_id' => $seller->id, 'is_retail_enabled' => true, 'is_wholesale_enabled' => true, 'wholesale_price' => 50, 'wholesale_min_quantity' => 10]);

    $expectedRetail = collect([$retail->id, $both->id])->sort()->values()->all();
    $expectedWholesale = collect([$wholesale->id, $both->id])->sort()->values()->all();

    $this->actingAs($seller->user)->get(route('seller.listings.index'))->assertInertia(fn ($page) => $page
        ->where('listings.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all() === $expectedRetail));
    $this->get(route('seller.wholesale.index'))->assertInertia(fn ($page) => $page
        ->component('seller/wholesale/index')
        ->where('listings.data', fn ($products) => collect($products)->pluck('id')->sort()->values()->all() === $expectedWholesale));
    $this->get(route('seller.wholesale.create'))->assertInertia(fn ($page) => $page
        ->component('seller/wholesale/create')->where('defaultChannel', 'wholesale'));
});

test('cart automatically switches pricing tiers at the wholesale minimum', function (): void {
    $listing = Listing::factory()->create([
        'price' => 100, 'sale_price' => null, 'is_retail_enabled' => true,
        'is_wholesale_enabled' => true, 'wholesale_price' => 70,
        'wholesale_min_quantity' => 10, 'stock_quantity' => 100,
    ]);

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 9])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.unitPrice', '100.00')->where('cart.items.0.pricingTier', 'retail')
        ->where('cart.subtotal', '900.00'));

    $this->patch(route('cart.items.update', $listing->id.'-base'), ['quantity' => 10])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.unitPrice', '70.00')->where('cart.items.0.pricingTier', 'wholesale')
        ->where('cart.items.0.minimumQuantity', 10)->where('cart.subtotal', '700.00'));
});

test('variant lines apply their own wholesale price and minimum independently', function (): void {
    $listing = Listing::factory()->create([
        'product_type' => 'variant', 'price' => 100, 'is_retail_enabled' => true,
        'is_wholesale_enabled' => true, 'wholesale_price' => 70,
        'wholesale_min_quantity' => 10, 'stock_quantity' => 200,
    ]);
    $first = ListingVariant::factory()->for($listing)->create([
        'selling_price' => 100, 'wholesale_price' => 70,
        'wholesale_min_quantity' => 10, 'stock_quantity' => 100, 'position' => 0,
    ]);
    $second = ListingVariant::factory()->for($listing)->create([
        'selling_price' => 200, 'wholesale_price' => 120,
        'wholesale_min_quantity' => 20, 'stock_quantity' => 100, 'position' => 1,
    ]);

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'listing_variant_id' => $first->id, 'quantity' => 10])->assertSessionHasNoErrors();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'listing_variant_id' => $second->id, 'quantity' => 19])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.pricingTier', 'wholesale')->where('cart.items.0.unitPrice', '70.00')
        ->where('cart.items.1.pricingTier', 'retail')->where('cart.items.1.unitPrice', '200.00'));

    $this->patch(route('cart.items.update', $listing->id.'-'.$second->id), ['quantity' => 20])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.1.pricingTier', 'wholesale')->where('cart.items.1.unitPrice', '120.00'));
});

test('wholesale only products reject quantities below their minimum and allow large valid orders', function (): void {
    $listing = Listing::factory()->create([
        'price' => null, 'sale_price' => null, 'is_retail_enabled' => false,
        'is_wholesale_enabled' => true, 'wholesale_price' => 5,
        'wholesale_min_quantity' => 500, 'stock_quantity' => 100000,
    ]);

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 499])->assertSessionHasErrors('quantity');
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 100000])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.pricingTier', 'wholesale')->where('cart.items.0.availableQuantity', 100000));
});

test('checkout snapshots the authoritative wholesale tier and price', function (): void {
    Notification::fake();
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create([
        'price' => 100, 'is_retail_enabled' => true, 'is_wholesale_enabled' => true,
        'wholesale_price' => 70, 'wholesale_min_quantity' => 10, 'stock_quantity' => 100,
    ]);
    $cart = Cart::factory()->for($buyer, 'buyer')->create();
    CartItem::factory()->for($cart)->for($listing)->create(['quantity' => 10]);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'recipient_name' => 'Wholesale Buyer', 'address_line_one' => '10 Market Street',
        'city' => 'Colombo', 'phone' => '0771234567',
    ])->assertRedirect(route('checkout.payment.show'));
    $this->post(route('checkout.payment.store'), ['payment_method' => 'cod'])->assertRedirect(route('checkout.review.show'));
    $this->post(route('checkout.review.store'), checkoutReviewData())->assertRedirect();

    $item = CustomerOrder::query()->whereBelongsTo($buyer, 'buyer')->sole()
        ->sellerOrders()->firstOrFail()->items()->sole();

    expect($item->unit_price)->toBe('70.00')->and($item->pricing_tier)->toBe('wholesale')->and($item->quantity)->toBe(10);
    Notification::assertSentTo($buyer, OrderAcknowledgmentNotification::class);
});
