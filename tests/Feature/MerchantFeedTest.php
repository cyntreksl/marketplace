<?php

use App\Models\Auction;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;

test('merchant feed exposes live buy-now catalog data with shipping and no session', function () {
    $listing = Listing::factory()->create([
        'title' => 'Sri Lankan Coffee Grinder',
        'description' => '<p>A durable grinder for fresh coffee.</p>',
        'condition' => 'new',
        'gtin' => '4006381333931',
        'price' => '12500.00',
    ]);
    ListingMedia::factory()->for($listing)->create([
        'disk' => 'r2',
        'path' => 'listings/grinder.webp',
        'processing_status' => 'ready',
        'variant_version' => 'v1',
        'variants' => ['card_2x' => 'listings/grinder-card-2x.webp'],
    ]);
    $auction = Listing::factory()->create(['listing_type' => 'auction', 'title' => 'Auction only camera']);
    Auction::factory()->for($auction)->create();

    $response = $this->get(route('feeds.google_merchant'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertHeader('Cache-Control')
        ->assertSee('<g:id>listing-'.$listing->id.'</g:id>', false)
        ->assertSee('<g:title>Sri Lankan Coffee Grinder</g:title>', false)
        ->assertSee('<g:gtin>4006381333931</g:gtin>', false)
        ->assertSee('<g:price>12500.00 LKR</g:price>', false)
        ->assertSee('<g:price>600.00 LKR</g:price>', false)
        ->assertSee('<g:min_handling_time>1</g:min_handling_time>', false)
        ->assertSee('<g:max_transit_time>5</g:max_transit_time>', false)
        ->assertDontSee('Auction only camera', false)
        ->assertCookieMissing((string) config('session.cookie'));
});

test('merchant feed emits active variants as stable grouped offers', function () {
    $listing = Listing::factory()->create([
        'product_type' => 'variant',
        'title' => 'Cotton T Shirt',
        'stock_quantity' => 5,
    ]);
    ListingMedia::factory()->for($listing)->create(['disk' => 'r2']);
    $variant = ListingVariant::factory()->for($listing)->create([
        'sku' => 'TEE-BLUE-M',
        'mpn' => 'BLUE-M',
        'selling_price' => '4500.00',
        'stock_quantity' => 2,
    ]);
    ListingVariant::factory()->for($listing)->create([
        'is_active' => false,
        'position' => 1,
        'sku' => 'INACTIVE',
    ]);

    $response = $this->get(route('feeds.google_merchant'));

    $response->assertOk()
        ->assertSee('<g:id>listing-'.$listing->id.'-variant-'.$variant->id.'</g:id>', false)
        ->assertSee('<g:item_group_id>listing-'.$listing->id.'</g:item_group_id>', false)
        ->assertSee('<g:mpn>BLUE-M</g:mpn>', false)
        ->assertDontSee('INACTIVE', false);
});
