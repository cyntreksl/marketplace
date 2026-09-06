<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Services\ListingSeoScoreService;

test('complete listing receives a strong 100 point advisory score', function () {
    $category = Category::factory()->create(['google_product_category_id' => 267]);
    $brand = Brand::factory()->create();
    $listing = Listing::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'title' => 'Premium Stainless Steel Coffee Grinder',
        'description' => str_repeat('Original product detail for Sri Lankan buyers. ', 8),
        'meta_title' => 'Premium Coffee Grinder in Sri Lanka',
        'meta_description' => 'Shop this durable stainless steel coffee grinder with clear local pricing, islandwide delivery, and secure checkout.',
        'gtin' => '4006381333931',
        'price' => '15000.00',
        'stock_quantity' => 8,
        'reserved_quantity' => 1,
        'specifications' => ['Material' => 'Stainless steel'],
    ]);

    ListingMedia::factory()->count(3)->for($listing)->create([
        'disk' => 'r2',
        'processing_status' => 'ready',
        'variant_version' => 'v1',
        'variants' => ['card_2x' => 'listings/product-card-2x.webp'],
    ]);

    $score = app(ListingSeoScoreService::class)->score(
        $listing->load(['auction', 'brand', 'category', 'media', 'variants']),
    );

    expect($score['score'])->toBe(100)
        ->and($score['maximum'])->toBe(100)
        ->and($score['label'])->toBe('Strong')
        ->and(collect($score['checks'])->every(fn (array $check): bool => $check['passed']))->toBeTrue();
});

test('incomplete listing receives correction guidance without changing approval', function () {
    $listing = Listing::factory()->create([
        'title' => 'Short',
        'description' => 'Tiny',
        'brand_id' => null,
        'meta_title' => null,
        'meta_description' => null,
        'price' => null,
        'stock_quantity' => 0,
        'specifications' => null,
        'warranty' => null,
    ]);

    $score = app(ListingSeoScoreService::class)->score(
        $listing->load(['auction', 'brand', 'category', 'media', 'variants']),
    );

    expect($score['label'])->toBe('Weak')
        ->and(collect($score['checks'])->whereNotNull('recommendation'))->not->toBeEmpty()
        ->and($listing->status)->toBe('approved');
});
