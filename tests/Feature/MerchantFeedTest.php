<?php

use App\Models\Auction;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Models\SellerProfile;

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

    $items = simplexml_load_string($response->getContent())->channel->item;
    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->children('http://base.google.com/ns/1.0')->id)
        ->toBe('listing-'.$listing->id.'-variant-'.$variant->id);
});

test('merchant feed contains the full approved catalog including sold out products', function () {
    $listings = Listing::factory()->count(12)
        ->has(ListingMedia::factory(), 'media')
        ->create();
    $soldOut = $listings->last();
    $soldOut->update(['stock_quantity' => 0, 'reserved_quantity' => 0, 'allow_backorders' => false]);

    foreach (['draft', 'pending_review', 'changes_requested', 'rejected', 'archived'] as $status) {
        Listing::factory()->has(ListingMedia::factory(), 'media')->create(['status' => $status]);
    }

    Listing::factory()->has(ListingMedia::factory(), 'media')->create(['is_active' => false]);
    Listing::factory()->has(ListingMedia::factory(), 'media')
        ->for(SellerProfile::factory()->state(['status' => 'pending_review']))
        ->create();
    Listing::factory()->has(ListingMedia::factory(), 'media')
        ->for(Category::factory()->state(['is_active' => false]))
        ->create();
    Listing::factory()->has(ListingMedia::factory(), 'media')
        ->for(Category::factory()->state(['is_taxonomy_available' => false]))
        ->create();
    Listing::factory()->has(ListingMedia::factory(), 'media')->create()->delete();

    $response = $this->get(route('feeds.google_merchant'))->assertOk();
    $items = simplexml_load_string($response->getContent())->channel->item;
    $offers = collect(iterator_to_array($items, false))
        ->mapWithKeys(function (SimpleXMLElement $item): array {
            $product = $item->children('http://base.google.com/ns/1.0');

            return [(string) $product->id => (string) $product->availability];
        });

    expect($offers)->toHaveCount(12)
        ->and($offers->keys()->all())->toBe($listings->map(fn (Listing $listing): string => 'listing-'.$listing->id)->all())
        ->and($offers['listing-'.$soldOut->id])->toBe('out_of_stock')
        ->and($offers->filter(fn (string $availability): bool => $availability === 'in_stock'))->toHaveCount(11);
});

test('new approvals enter the feed and sitemap and archival removes them', function () {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    $listing = Listing::factory()->has(ListingMedia::factory(), 'media')->create(['status' => 'pending_review']);
    $offerId = '<g:id>listing-'.$listing->id.'</g:id>';

    $this->get(route('feeds.google_merchant'))->assertOk()->assertDontSee($offerId, false);
    $this->get(route('sitemap.products', 1))->assertOk()->assertDontSee(route('listings.show', $listing->slug), false);

    $listing->update(['status' => 'approved']);

    $this->get(route('feeds.google_merchant'))->assertOk()->assertSee($offerId, false);
    $this->get(route('sitemap.products', 1))->assertOk()->assertSee(route('listings.show', $listing->slug), false);

    $listing->update(['status' => 'archived']);

    $this->get(route('feeds.google_merchant'))->assertOk()->assertDontSee($offerId, false);
    $this->get(route('sitemap.products', 1))->assertOk()->assertDontSee(route('listings.show', $listing->slug), false);
});
