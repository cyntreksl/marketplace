<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WholesalePriceTier;
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
        'wholesale_tiers' => [
            ['minimum_quantity' => 1, 'unit_price' => 50],
        ],
    ])->assertSessionHasErrors('wholesale_tiers.0.minimum_quantity');

    $this->post(route('seller.listings.store'), [
        ...$payload('PRICE-1'),
        'is_retail_enabled' => true,
        'is_wholesale_enabled' => true,
        'selling_price' => 100,
        'compare_price' => 150,
        'wholesale_tiers' => [
            ['minimum_quantity' => 2, 'unit_price' => 100],
        ],
    ])->assertSessionHasErrors('wholesale_tiers.0.unit_price');

    $this->post(route('seller.listings.store'), [
        ...$payload('ORDER-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'wholesale_tiers' => [
            ['minimum_quantity' => 3, 'unit_price' => 1000],
            ['minimum_quantity' => 10, 'unit_price' => 1100],
        ],
    ])->assertSessionHasErrors('wholesale_tiers.1.unit_price');

    $this->post(route('seller.listings.store'), [
        ...$payload('DUPLICATE-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'wholesale_tiers' => [
            ['minimum_quantity' => 10, 'unit_price' => 1000],
            ['minimum_quantity' => 10, 'unit_price' => 900],
        ],
    ])->assertSessionHasErrors('wholesale_tiers.1.minimum_quantity');

    $this->post(route('seller.listings.store'), [
        ...$payload('COUNT-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'wholesale_tiers' => [
            ['minimum_quantity' => 3, 'unit_price' => 1000],
            ['minimum_quantity' => 10, 'unit_price' => 900],
            ['minimum_quantity' => 100, 'unit_price' => 600],
            ['minimum_quantity' => 1000, 'unit_price' => 500],
        ],
    ])->assertSessionHasErrors('wholesale_tiers');

    $this->post(route('seller.listings.store'), [
        ...$payload('VALID-1'),
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'wholesale_tiers' => [
            ['minimum_quantity' => 2, 'unit_price' => 50],
        ],
    ])->assertSessionHasNoErrors();

    expect(Listing::query()->sole())->status->toBe('pending_review')
        ->price->toBeNull()
        ->wholesale_price->toBe('50.00')
        ->wholesale_min_quantity->toBe(2);
    expect(WholesalePriceTier::query()->sole())
        ->minimum_quantity->toBe(2)
        ->unit_price->toBe('50.00');
});

test('seller variant wholesale tiers persist independently and derive listing summaries', function (): void {
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)->post(route('seller.listings.store'), [
        'category_id' => $category->id,
        'brand_name' => 'Bulk Variants',
        'sku' => 'BULK-SHIRT',
        'title' => 'Bulk shirts',
        'description' => 'Shirts supplied in independently priced wholesale variants.',
        'condition' => 'new',
        'product_type' => 'variant',
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => true,
        'low_stock_threshold' => 2,
        'allow_backorders' => false,
        'is_active' => true,
        'is_featured' => false,
        'is_best_seller' => false,
        'is_new_arrival' => false,
        'variant_options' => [
            ['name' => 'Color', 'values' => ['Red', 'Blue']],
        ],
        'variants' => [
            [
                'selections' => ['Red'],
                'sku' => 'BULK-SHIRT-RED',
                'stock_quantity' => 500,
                'is_active' => true,
                'wholesale_tiers' => [
                    ['minimum_quantity' => 3, 'unit_price' => 1000],
                    ['minimum_quantity' => 10, 'unit_price' => 900],
                    ['minimum_quantity' => 100, 'unit_price' => 600],
                ],
            ],
            [
                'selections' => ['Blue'],
                'sku' => 'BULK-SHIRT-BLUE',
                'stock_quantity' => 500,
                'is_active' => true,
                'wholesale_tiers' => [
                    ['minimum_quantity' => 5, 'unit_price' => 1100],
                    ['minimum_quantity' => 50, 'unit_price' => 700],
                ],
            ],
        ],
        'images' => [UploadedFile::fake()->image('shirts.jpg', 1200, 1200)],
        'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1200]],
        'submit_for_review' => true,
    ])->assertSessionHasNoErrors();

    $listing = Listing::query()->sole();
    $variants = $listing->variants()->with('wholesalePriceTiers')->orderBy('position')->get();

    expect($listing)
        ->wholesale_price->toBe('600.00')
        ->wholesale_min_quantity->toBe(100)
        ->and($variants[0]->wholesale_price)->toBe('600.00')
        ->and($variants[0]->wholesale_min_quantity)->toBe(100)
        ->and($variants[0]->wholesalePriceTiers)->toHaveCount(3)
        ->and($variants[1]->wholesale_price)->toBe('700.00')
        ->and($variants[1]->wholesale_min_quantity)->toBe(50)
        ->and($variants[1]->wholesalePriceTiers)->toHaveCount(2);
});

test('legacy scalar wholesale prices are backfilled without changing effective values', function (): void {
    $simple = Listing::factory()->create([
        'product_type' => 'simple',
        'wholesale_price' => 45,
        'wholesale_min_quantity' => 25,
    ]);
    $variantListing = Listing::factory()->create(['product_type' => 'variant']);
    $variant = ListingVariant::factory()->for($variantListing)->create([
        'wholesale_price' => 80,
        'wholesale_min_quantity' => 12,
    ]);

    $migration = require database_path('migrations/2026_09_08_205546_backfill_wholesale_price_tiers.php');
    $migration->up();

    expect($simple->wholesalePriceTiers()->sole())
        ->minimum_quantity->toBe(25)
        ->unit_price->toBe('45.00')
        ->and($variant->wholesalePriceTiers()->sole())
        ->minimum_quantity->toBe(12)
        ->unit_price->toBe('80.00');
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

test('cart automatically applies the greatest qualifying wholesale tier', function (): void {
    $listing = Listing::factory()->create([
        'price' => 100, 'sale_price' => null, 'is_retail_enabled' => true,
        'is_wholesale_enabled' => true, 'wholesale_price' => 60,
        'wholesale_min_quantity' => 100, 'stock_quantity' => 100,
    ]);
    $listing->wholesalePriceTiers()->createMany([
        ['minimum_quantity' => 3, 'unit_price' => 90],
        ['minimum_quantity' => 10, 'unit_price' => 70],
        ['minimum_quantity' => 100, 'unit_price' => 60],
    ]);

    $this->get(route('listings.show', [
        'listing' => $listing->slug,
        'wholesale' => 1,
    ]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('purchaseContext.channel', 'wholesale')
        ->where('purchaseContext.initialQuantity', 3)
        ->where('listing.wholesaleTiers.0.minimumQuantity', 3)
        ->where('listing.wholesaleTiers.1.minimumQuantity', 10)
        ->where('listing.wholesaleTiers.2.minimumQuantity', 100));

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.unitPrice', '100.00')->where('cart.items.0.pricingTier', 'retail')
        ->where('cart.items.0.minimumQuantity', 1)
        ->where('cart.items.0.appliedTierMinimumQuantity', null)
        ->where('cart.subtotal', '200.00'));

    $this->patch(route('cart.items.update', $listing->id.'-base'), ['quantity' => 3])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.unitPrice', '90.00')->where('cart.items.0.pricingTier', 'wholesale')
        ->where('cart.items.0.minimumQuantity', 1)
        ->where('cart.items.0.appliedTierMinimumQuantity', 3)
        ->where('cart.subtotal', '270.00'));

    $this->patch(route('cart.items.update', $listing->id.'-base'), ['quantity' => 10])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.unitPrice', '70.00')
        ->where('cart.items.0.appliedTierMinimumQuantity', 10)
        ->where('cart.subtotal', '700.00'));

    $this->patch(route('cart.items.update', $listing->id.'-base'), ['quantity' => 100])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page
        ->where('cart.items.0.unitPrice', '60.00')
        ->where('cart.items.0.appliedTierMinimumQuantity', 100)
        ->where('cart.subtotal', '6000.00'));
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
    $first->wholesalePriceTiers()->createMany([
        ['listing_id' => $listing->id, 'minimum_quantity' => 10, 'unit_price' => 70],
    ]);
    $second->wholesalePriceTiers()->createMany([
        ['listing_id' => $listing->id, 'minimum_quantity' => 20, 'unit_price' => 120],
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
    $listing->wholesalePriceTiers()->create([
        'minimum_quantity' => 500,
        'unit_price' => 5,
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
    $listing->wholesalePriceTiers()->createMany([
        ['minimum_quantity' => 3, 'unit_price' => 90],
        ['minimum_quantity' => 10, 'unit_price' => 70],
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
