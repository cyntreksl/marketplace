<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function variantProductPayload(Category $category, array $overrides = []): array
{
    return [
        'category_id' => $category->id,
        'brand_name' => 'Northstar Goods',
        'sku' => 'SHIRT-001',
        'title' => 'Everyday cotton shirt',
        'short_description' => 'A breathable everyday shirt.',
        'description' => '<p>Soft cotton shirt with a comfortable regular fit.</p>',
        'condition' => 'new',
        'product_type' => 'variant',
        'location' => 'Colombo',
        'selling_price' => '4500.00',
        'compare_price' => '5000.00',
        'low_stock_threshold' => 2,
        'allow_backorders' => false,
        'is_active' => true,
        'is_featured' => true,
        'is_best_seller' => true,
        'is_new_arrival' => true,
        'meta_title' => 'Everyday Cotton Shirt',
        'meta_description' => 'Shop a comfortable cotton shirt in several colors and sizes.',
        'variant_options' => [
            ['name' => 'Color', 'values' => ['Red', 'Blue']],
            ['name' => 'Size', 'values' => ['Small', 'Large']],
        ],
        'variants' => [
            ['selections' => ['Red', 'Small'], 'sku' => 'SHIRT-RED-S', 'selling_price' => '4500.00', 'market_price' => '5000.00', 'stock_quantity' => 1, 'is_active' => true],
            ['selections' => ['Red', 'Large'], 'sku' => 'SHIRT-RED-L', 'selling_price' => '4500.00', 'market_price' => '5000.00', 'stock_quantity' => 2, 'is_active' => true],
            ['selections' => ['Blue', 'Small'], 'sku' => 'SHIRT-BLUE-S', 'selling_price' => '4500.00', 'market_price' => '5000.00', 'stock_quantity' => 3, 'is_active' => true],
            ['selections' => ['Blue', 'Large'], 'sku' => 'SHIRT-BLUE-L', 'selling_price' => '4500.00', 'market_price' => '5000.00', 'stock_quantity' => 4, 'is_active' => true],
        ],
        ...$overrides,
    ];
}

test('a seller can save and edit a completely incomplete product draft', function () {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [])
        ->assertRedirect(route('seller.listings.index', absolute: false))
        ->assertSessionHasNoErrors();

    $listing = Listing::query()->sole();

    expect($listing)
        ->status->toBe('draft')
        ->title->toBeNull()
        ->slug->toBeNull()
        ->category_id->toBeNull()
        ->sku->toBeNull()
        ->product_type->toBe('simple')
        ->is_active->toBeTrue();

    $this->actingAs($seller->user)
        ->get(route('seller.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/listings/edit')
            ->where('listing.id', $listing->id)
            ->where('listing.sku', null)
            ->where('listing.product_type', 'simple')
            ->where('listing.variant_options', [])
            ->where('listing.variants', []));
});

test('seller product forms ignore legacy cost price input', function () {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), ['cost_price' => '2500.00'])
        ->assertSessionHasNoErrors();

    expect(Listing::query()->sole()->cost_price)->toBeNull();
});

test('submission requires a complete product while drafts still enforce pricing safety', function () {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), ['submit_for_review' => true])
        ->assertSessionHasErrors([
            'category_id',
            'brand_id',
            'sku',
            'title',
            'description',
            'condition',
            'selling_price',
            'images',
        ]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'selling_price' => '5000.00',
            'compare_price' => '5000.00',
        ])
        ->assertSessionHasErrors('compare_price');

    expect(Listing::query()->count())->toBe(0);
});

test('a complete variant product generates the exact matrix and aggregate inventory', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $payload = variantProductPayload($category, [
        'images' => [UploadedFile::fake()->image('shirt.jpg', 1600, 1600)],
        'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
        'submit_for_review' => true,
    ]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), $payload)
        ->assertRedirect(route('seller.listings.index', absolute: false))
        ->assertSessionHasNoErrors();

    $listing = Listing::query()->sole();

    expect($listing)
        ->status->toBe('pending_review')
        ->product_type->toBe('variant')
        ->stock_quantity->toBe(10)
        ->price->toBe('5000.00')
        ->sale_price->toBe('4500.00')
        ->cost_price->toBeNull()
        ->is_featured->toBeTrue()
        ->is_best_seller->toBeTrue()
        ->is_new_arrival->toBeTrue()
        ->and($listing->variantOptions()->count())->toBe(2)
        ->and($listing->variants()->count())->toBe(4)
        ->and($listing->variants()->where('is_active', true)->count())->toBe(4)
        ->and($listing->variants()->firstOrFail()->selling_price)->toBe('4500.00')
        ->and($listing->variants()->firstOrFail()->market_price)->toBe('5000.00')
        ->and($listing->variants()->pluck('sku')->all())->toBe([
            'SHIRT-RED-S',
            'SHIRT-RED-L',
            'SHIRT-BLUE-S',
            'SHIRT-BLUE-L',
        ]);

    $this->actingAs($seller->user)
        ->get(route('seller.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.sku', 'SHIRT-001')
            ->where('listing.meta_title', 'Everyday Cotton Shirt')
            ->where('listing.variant_options.0.name', 'Color')
            ->where('listing.variant_options.1.name', 'Size')
            ->where('listing.variants.0.selling_price', '4500.00')
            ->where('listing.variants.0.market_price', '5000.00')
            ->where('listing.variants.0.is_active', true)
            ->has('listing.variants', 4));
});

test('variant pricing and status determine the product summary', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [['name' => 'Capacity', 'values' => ['128 GB', '256 GB']]],
            'variants' => [
                [
                    'selections' => ['128 GB'],
                    'sku' => 'PHONE-128',
                    'selling_price' => '120000.00',
                    'market_price' => '135000.00',
                    'stock_quantity' => 5,
                    'is_active' => true,
                ],
                [
                    'selections' => ['256 GB'],
                    'sku' => 'PHONE-256',
                    'selling_price' => '110000.00',
                    'market_price' => '140000.00',
                    'stock_quantity' => 20,
                    'is_active' => false,
                ],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $listing = Listing::query()->sole();

    expect($listing)
        ->stock_quantity->toBe(5)
        ->price->toBe('135000.00')
        ->sale_price->toBe('120000.00')
        ->and($listing->variants()->where('is_active', false)->sole()->stock_quantity)->toBe(20);
});

test('active variants require valid selling and market prices when publishing', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $basePayload = variantProductPayload($category, [
        'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
        'images' => [UploadedFile::fake()->image('shirt.jpg', 1600, 1600)],
        'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
        'submit_for_review' => true,
    ]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            ...$basePayload,
            'variants' => [[
                'selections' => ['Red'],
                'sku' => 'SHIRT-RED',
                'selling_price' => '',
                'market_price' => '5000.00',
                'stock_quantity' => 2,
                'is_active' => true,
            ]],
        ])
        ->assertSessionHasErrors('variants.0.selling_price');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            ...$basePayload,
            'images' => [UploadedFile::fake()->image('shirt-again.jpg', 1600, 1600)],
            'variants' => [[
                'selections' => ['Red'],
                'sku' => 'SHIRT-RED',
                'selling_price' => '5000.00',
                'market_price' => '4500.00',
                'stock_quantity' => 2,
                'is_active' => true,
            ]],
        ])
        ->assertSessionHasErrors('variants.0.market_price');

    expect(Listing::query()->count())->toBe(0);
});

test('variant validation rejects duplicates overflow and an inexact cartesian matrix', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [
                ['name' => 'Color', 'values' => ['Red']],
                ['name' => 'color', 'values' => ['Blue']],
            ],
            'variants' => [
                ['selections' => ['Red', 'Blue'], 'sku' => 'ONE', 'stock_quantity' => 1],
            ],
        ]))
        ->assertSessionHasErrors('variant_options');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red', 'red']]],
            'variants' => [
                ['selections' => ['Red'], 'sku' => 'DUPLICATE', 'stock_quantity' => 1],
                ['selections' => ['red'], 'sku' => 'DUPLICATE', 'stock_quantity' => 1],
            ],
        ]))
        ->assertSessionHasErrors(['variant_options.0.values', 'variants']);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [
                ['name' => 'Color', 'values' => range(1, 5)],
                ['name' => 'Size', 'values' => range(1, 5)],
                ['name' => 'Material', 'values' => range(1, 5)],
            ],
            'variants' => [],
        ]))
        ->assertSessionHasErrors('variant_options');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red', 'Blue']]],
            'variants' => [
                ['selections' => ['Red'], 'sku' => 'RED', 'stock_quantity' => 1],
                ['selections' => ['Green'], 'sku' => 'GREEN', 'stock_quantity' => 1],
            ],
        ]))
        ->assertSessionHasErrors('variants');

    expect(Listing::query()->count())->toBe(0);
});

test('base and variant skus are unique within a seller catalog', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'sku' => 'TAKEN-SKU',
    ]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'sku' => 'TAKEN-SKU',
        ])
        ->assertSessionHasErrors('sku');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'sku' => 'AVAILABLE-BASE',
            'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
            'variants' => [
                ['selections' => ['Red'], 'sku' => 'TAKEN-SKU', 'stock_quantity' => 1],
            ],
        ]))
        ->assertSessionHasErrors('variants');

    $otherSeller = SellerProfile::factory()->create();

    $this->actingAs($otherSeller->user)
        ->post(route('seller.listings.store'), [
            'sku' => 'TAKEN-SKU',
        ])
        ->assertSessionHasNoErrors();
});

test('editing options keeps unchanged inventory suggests new skus and removes obsolete rows', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
            'variants' => [
                ['selections' => ['Red'], 'sku' => 'CUSTOM-RED', 'stock_quantity' => 7],
            ],
        ]));

    $listing = Listing::query()->sole();

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red', 'Blue']]],
            'variants' => [
                ['selections' => ['Red'], 'sku' => 'CUSTOM-RED', 'stock_quantity' => 7],
                ['selections' => ['Blue'], 'sku' => '', 'stock_quantity' => 0],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->refresh()->stock_quantity)->toBe(7)
        ->and($listing->variants()->orderBy('position')->pluck('sku')->all())->toBe([
            'CUSTOM-RED',
            'SHIRT-001-BLUE',
        ]);

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Blue']]],
            'variants' => [
                ['selections' => ['Blue'], 'sku' => 'SHIRT-001-BLUE', 'stock_quantity' => 0],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->refresh()->variants()->count())->toBe(1)
        ->and($listing->variants()->sole()->sku)->toBe('SHIRT-001-BLUE');
});

test('variant combination images are optional and persist with an unchanged combination', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $payload = variantProductPayload($category, [
        'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
        'variants' => [[
            'selections' => ['Red'],
            'sku' => 'SHIRT-RED',
            'stock_quantity' => 3,
            'image' => UploadedFile::fake()->image('red-shirt.jpg', 1200, 1200),
            'image_crop' => ['x' => 100, 'y' => 100, 'width' => 1000, 'height' => 1000],
        ]],
    ]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), $payload)
        ->assertRedirect(route('seller.listings.index', absolute: false))
        ->assertSessionHasNoErrors();

    $listing = Listing::query()->sole();
    $variant = $listing->variants()->with('image')->sole();
    $image = $variant->image;

    expect($listing->media()->count())->toBe(0)
        ->and($image)->not->toBeNull()
        ->and($image->type)->toBe('variant_image')
        ->and($image->listing_id)->toBe($listing->id)
        ->and($image->crop_x)->toBe(100)
        ->and($image->crop_y)->toBe(100)
        ->and($image->crop_width)->toBe(1000)
        ->and($image->crop_height)->toBe(1000)
        ->and(Storage::disk('public')->exists($image->path))->toBeTrue();

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
            'variants' => [[
                'selections' => ['Red'],
                'sku' => 'SHIRT-RED',
                'stock_quantity' => 4,
            ]],
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->variants()->with('image')->sole()->image?->id)->toBe($image->id);

    $this->actingAs($seller->user)
        ->get(route('seller.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.variants.0.image.id', $image->id)
            ->where('listing.variants.0.image.url', $image->url));

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
            'variants' => [[
                'selections' => ['Red'],
                'sku' => 'SHIRT-RED',
                'stock_quantity' => 4,
                'remove_image' => true,
            ]],
        ]))
        ->assertSessionHasNoErrors();

    expect($listing->variants()->with('image')->sole()->image)->toBeNull()
        ->and(ListingMedia::query()->find($image->id))->toBeNull()
        ->and(Storage::disk('public')->missing($image->path))->toBeTrue();
});

test('variant image uploads require a saved valid crop', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $variant = [
        'selections' => ['Red'],
        'sku' => 'SHIRT-RED',
        'stock_quantity' => 3,
        'image' => UploadedFile::fake()->image('red-shirt.jpg', 1200, 1200),
    ];
    $payload = variantProductPayload($category, [
        'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
        'variants' => [$variant],
    ]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), $payload)
        ->assertSessionHasErrors('variants.0.image_crop');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            ...$payload,
            'variants' => [[
                ...$variant,
                'image' => UploadedFile::fake()->image('red-shirt.jpg', 1200, 1200),
                'image_crop' => ['x' => 500, 'y' => 400, 'width' => 1000, 'height' => 1000],
            ]],
        ])
        ->assertSessionHasErrors('variants.0.image_crop');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            ...$payload,
            'variants' => [[
                ...$variant,
                'image' => UploadedFile::fake()->image('red-shirt.jpg', 1200, 1200),
                'image_crop' => ['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 1100],
            ]],
        ])
        ->assertSessionHasErrors('variants.0.image_crop');

    expect(Listing::query()->count())->toBe(0);
});

test('variant image uploads accept smaller square source crops', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), variantProductPayload($category, [
            'variant_options' => [['name' => 'Color', 'values' => ['Red']]],
            'variants' => [[
                'selections' => ['Red'],
                'sku' => 'SHIRT-RED',
                'stock_quantity' => 3,
                'image' => UploadedFile::fake()->image('red-shirt.jpg', 900, 900),
                'image_crop' => ['x' => 0, 'y' => 0, 'width' => 900, 'height' => 900],
            ]],
        ]))
        ->assertRedirect(route('seller.listings.index', absolute: false))
        ->assertSessionHasNoErrors();

    $image = Listing::query()->sole()->variants()->with('image')->sole()->image;

    expect($image)->not->toBeNull()
        ->and($image->crop_width)->toBe(900)
        ->and($image->crop_height)->toBe(900);
});

test('active availability backorders and stock status control public purchasing', function () {
    $inactive = Listing::factory()->create(['is_active' => false, 'stock_quantity' => 10]);
    $outOfStock = Listing::factory()->create(['stock_quantity' => 0, 'allow_backorders' => false]);
    $backorder = Listing::factory()->create(['stock_quantity' => 0, 'allow_backorders' => true]);
    $lowStock = Listing::factory()->create(['stock_quantity' => 2, 'low_stock_threshold' => 3]);

    $publicIds = Listing::query()->publiclyVisible()->pluck('listings.id');

    expect($publicIds)
        ->not->toContain($inactive->id)
        ->not->toContain($outOfStock->id)
        ->toContain($backorder->id)
        ->toContain($lowStock->id)
        ->and($inactive->stockStatus())->toBe('in_stock')
        ->and($outOfStock->stockStatus())->toBe('out_of_stock')
        ->and($backorder->stockStatus())->toBe('backorder')
        ->and($lowStock->stockStatus())->toBe('low_stock');

    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->post(route('cart.items.store'), [
            'listing_id' => $backorder->id,
            'quantity' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($buyer)
        ->post(route('checkout.store'), [
            'payment_method' => 'cod',
            'recipient_name' => 'Backorder Buyer',
            'address_line_one' => '10 Galle Road',
            'city' => 'Colombo',
            'phone' => '0771234567',
        ])
        ->assertRedirect(route('buyer.orders.index', absolute: false))
        ->assertSessionHasNoErrors();

    expect($backorder->refresh()->reserved_quantity)->toBe(2);
});

test('seo and short description fields are exposed without leaking cost price', function () {
    $listing = Listing::factory()->create([
        'short_description' => 'A concise product summary.',
        'meta_title' => 'Custom Search Title',
        'meta_description' => 'Custom search description.',
        'cost_price' => '1000.00',
    ]);

    $response = $this->get(route('listings.show', $listing->slug));

    $response->assertOk()
        ->assertSee('property="og:title" content="Custom Search Title"', escape: false)
        ->assertSee('name="description" content="Custom search description."', escape: false)
        ->assertInertia(fn ($page) => $page
            ->where('listing.shortDescription', 'A concise product summary.')
            ->where('listing.metaTitle', 'Custom Search Title')
            ->where('listing.metaDescription', 'Custom search description.')
            ->missing('listing.costPrice')
            ->missing('listing.cost_price'));
});

test('a seller can remove an existing product image while retaining the next cover', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
    ]);
    $cover = ListingMedia::factory()->for($listing)->create(['sort_order' => 0]);
    $remaining = ListingMedia::factory()->for($listing)->create(['sort_order' => 1]);

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), [
            'removed_media_ids' => [$cover->id],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false))
        ->assertSessionHasNoErrors();

    expect(ListingMedia::query()->find($cover->id))->toBeNull()
        ->and($listing->media()->first()->is($remaining))->toBeTrue();
});
