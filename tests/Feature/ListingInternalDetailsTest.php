<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\SellerProfile;

function internalDetailsUrl(Listing $listing): string
{
    return route('seller.listings.internal-details.update', $listing);
}

test('seller creates preserves and clears optional private product details', function () {
    $seller = SellerProfile::factory()->create();
    $this->actingAs($seller->user)->post(route('seller.listings.store'), [
        'cost_price' => '0', 'supplier_name' => 'Private supplier', 'internal_notes' => "Buy in cartons\nCall first",
    ])->assertSessionHasNoErrors();
    $listing = Listing::sole();
    expect($listing->cost_price)->toBe('0.00')->and($listing->supplier_name)->toBe('Private supplier');
    $this->put(route('seller.listings.update', $listing), ['title' => 'Changed title'])->assertSessionHasNoErrors();
    expect($listing->refresh()->cost_price)->toBe('0.00')->and($listing->supplier_name)->toBe('Private supplier');
    $this->get(route('seller.listings.edit', $listing))->assertInertia(fn ($page) => $page
        ->where('listing.cost_price', '0.00')->where('listing.supplier_name', 'Private supplier')
        ->where('listing.internal_notes', "Buy in cartons\nCall first"));
    $this->put(route('seller.listings.update', $listing), ['cost_price' => '', 'supplier_name' => '', 'internal_notes' => ''])->assertSessionHasNoErrors();
    expect($listing->refresh()->cost_price)->toBeNull()->and($listing->supplier_name)->toBeNull()->and($listing->internal_notes)->toBeNull();
});

test('private updates preserve live product state and public fields', function (string $status) {
    $listing = Listing::factory()->create(['status' => $status, 'cost_price' => '900.00']);
    $before = $listing->only(['title', 'status', 'approved_at', 'stock_quantity', 'reserved_quantity', 'price', 'sale_price']);
    $this->actingAs($listing->sellerProfile->user)->patch(internalDetailsUrl($listing), [
        'cost_price' => '1250.50', 'supplier_name' => 'Internal supplier', 'internal_notes' => 'Private note',
        'status' => 'draft', 'price' => 1, 'stock_quantity' => 999, 'title' => 'Malicious change',
    ])->assertSessionHasNoErrors();
    expect($listing->refresh()->only(array_keys($before)))->toEqual($before)
        ->and($listing->cost_price)->toBe('1250.50')
        ->and(AuditLog::where('action', 'listing.internal_details_updated')->count())->toBe(1);
})->with(['draft', 'changes_requested', 'rejected', 'pending_review', 'approved']);

test('archived products and other sellers cannot receive private updates', function () {
    $listing = Listing::factory()->create();
    $other = SellerProfile::factory()->create();
    $this->actingAs($other->user)->patch(internalDetailsUrl($listing), ['cost_price' => '1'])->assertForbidden();
    $listing->update(['status' => 'archived']);
    $this->actingAs($listing->sellerProfile->user)->patch(internalDetailsUrl($listing), ['cost_price' => '1'])->assertForbidden();
    expect($listing->refresh()->cost_price)->toBeNull();
});

test('variant private costs update independently and reject foreign variant ids atomically', function () {
    $listing = Listing::factory()->create(['product_type' => 'variant']);
    $first = ListingVariant::factory()->create(['listing_id' => $listing->id, 'cost_price' => '20.00']);
    $second = ListingVariant::factory()->create(['listing_id' => $listing->id, 'position' => 1, 'cost_price' => '30.00']);
    $foreign = ListingVariant::factory()->create();
    $this->actingAs($listing->sellerProfile->user)->patch(internalDetailsUrl($listing), [
        'supplier_name' => 'Do not save', 'variants' => [['id' => $first->id, 'cost_price' => '50'], ['id' => $foreign->id, 'cost_price' => '90']],
    ])->assertSessionHasErrors('variants.1.id');
    expect($first->refresh()->cost_price)->toBe('20.00')->and($listing->refresh()->supplier_name)->toBeNull();
    $this->patch(internalDetailsUrl($listing), ['variants' => [['id' => $second->id, 'cost_price' => '0'], ['id' => $first->id, 'cost_price' => null]]])->assertSessionHasNoErrors();
    expect($first->refresh()->cost_price)->toBeNull()->and($second->refresh()->cost_price)->toBe('0.00');
});

test('private costs are validated on both draft and live endpoints', function (mixed $cost) {
    $listing = Listing::factory()->create();
    $this->actingAs($listing->sellerProfile->user)->patch(internalDetailsUrl($listing), ['cost_price' => $cost])->assertSessionHasErrors('cost_price');
    $this->post(route('seller.listings.store'), ['cost_price' => $cost])->assertSessionHasErrors('cost_price');
})->with([-1, '1.001', '10000000000', 'invalid']);

test('private field lengths and variant identifiers are validated', function () {
    $listing = Listing::factory()->create(['product_type' => 'variant']);
    $variant = ListingVariant::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($listing->sellerProfile->user)->patch(internalDetailsUrl($listing), [
        'supplier_name' => str_repeat('s', 256), 'internal_notes' => str_repeat('n', 10001),
        'cost_price' => '1', 'variants' => [['id' => $variant->id, 'cost_price' => '1'], ['id' => $variant->id, 'cost_price' => '2']],
    ])->assertSessionHasErrors(['supplier_name', 'internal_notes', 'cost_price', 'variants.0.id']);
});

test('private product and variant fields are absent from default serialization and storefront responses', function () {
    $listing = Listing::factory()->create(['product_type' => 'variant', 'supplier_name' => 'SECRET-SUPPLIER', 'internal_notes' => 'SECRET-NOTE', 'cost_price' => '123.45']);
    $variant = ListingVariant::factory()->create(['listing_id' => $listing->id, 'cost_price' => '987.65', 'stock_quantity' => 3]);
    expect($listing->toArray())->not->toHaveKeys(['supplier_name', 'internal_notes', 'cost_price']);
    expect($variant->toArray())->not->toHaveKey('cost_price');
    $response = $this->get(route('listings.show', $listing->slug))->assertOk();
    $response->assertDontSee('SECRET-SUPPLIER')->assertDontSee('SECRET-NOTE')->assertDontSee('987.65');
    $this->actingAs($listing->sellerProfile->user)->get(route('seller.listings.edit', $listing))
        ->assertInertia(fn ($page) => $page->where('listing.supplier_name', 'SECRET-SUPPLIER')->where('listing.variants.0.cost_price', '987.65'));
});

test('draft variant costs survive reordering and omitted input and can be cleared', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $payload = [
        'category_id' => $category->id, 'title' => 'Shirt', 'sku' => 'SHIRT', 'product_type' => 'variant',
        'variant_options' => [['name' => 'Size', 'values' => ['Small', 'Large']]],
        'variants' => [
            ['selections' => ['Small'], 'sku' => 'SHIRT-RED-S', 'selling_price' => '2000', 'is_active' => true],
            ['selections' => ['Large'], 'sku' => 'SHIRT-RED-L', 'selling_price' => '2200', 'is_active' => true],
        ],
    ];
    $payload['variants'][0]['cost_price'] = '1500.25';
    $payload['variants'][1]['cost_price'] = '1700.00';
    $this->actingAs($seller->user)->post(route('seller.listings.store'), $payload)->assertSessionHasNoErrors();
    $listing = Listing::sole();
    $first = $listing->variants()->where('sku', 'SHIRT-RED-S')->sole();
    $second = $listing->variants()->where('sku', 'SHIRT-RED-L')->sole();
    unset($payload['variants'][0]['cost_price']);
    $payload['variants'][1]['cost_price'] = null;
    $payload['variants'] = array_reverse($payload['variants']);
    $payload['variant_options'][0]['values'] = ['Large', 'Small'];
    $this->put(route('seller.listings.update', $listing), $payload)->assertSessionHasNoErrors();
    expect($first->refresh()->cost_price)->toBe('1500.25')->and($second->refresh()->cost_price)->toBeNull()
        ->and($listing->refresh()->cost_price)->toBeNull();
});

test('private endpoint preserves omitted costs and rejects variant data on simple products', function () {
    $listing = Listing::factory()->create(['cost_price' => '99.99']);
    $this->actingAs($listing->sellerProfile->user)->patch(internalDetailsUrl($listing), ['internal_notes' => 'New note'])->assertSessionHasNoErrors();
    expect($listing->refresh()->cost_price)->toBe('99.99');
    $this->patch(internalDetailsUrl($listing), ['variants' => [['id' => 999, 'cost_price' => '1']]])->assertSessionHasErrors('variants');
});
