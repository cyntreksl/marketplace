<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;

test('valid GTIN lengths and checksums are accepted while identifiers remain optional', function (string $gtin) {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'sku' => 'PRODUCT-'.$gtin,
            'barcode' => 'INTERNAL-'.$gtin,
            'gtin' => $gtin,
            'mpn' => 'MAKER-'.$gtin,
        ])
        ->assertSessionHasNoErrors();

    expect(Listing::query()->sole())
        ->gtin->toBe($gtin)
        ->mpn->toBe('MAKER-'.$gtin)
        ->barcode->toBe('INTERNAL-'.$gtin);
})->with([
    'GTIN-8' => '96385074',
    'UPC / GTIN-12' => '012345678905',
    'EAN / GTIN-13' => '4006381333931',
    'GTIN-14' => '10012345678902',
]);

test('empty identifiers are optional and legacy barcodes are never promoted to GTIN', function () {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), ['barcode' => '1231', 'gtin' => '', 'mpn' => ''])
        ->assertSessionHasNoErrors();

    expect(Listing::query()->sole())
        ->barcode->toBe('1231')
        ->gtin->toBeNull()
        ->mpn->toBeNull();
});

test('GTIN validation rejects invalid lengths non-digits and check digits', function (string $gtin) {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), ['gtin' => $gtin])
        ->assertSessionHasErrors('gtin');

    expect(Listing::query()->count())->toBe(0);
})->with([
    'invalid length' => '123456789',
    'non-digits' => '400638133393A',
    'invalid checksum' => '4006381333932',
]);

test('SKUs use printable ASCII without whitespace when supplied', function (string $sku) {
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), ['sku' => $sku])
        ->assertSessionHasErrors('sku');
})->with(['space' => 'PRODUCT 001', 'tab' => "PRODUCT\t001", 'non-ASCII' => 'PRODUCT-ලංකා']);

test('variant GTINs must be distinct within a product', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'product_type' => 'variant',
            'sku' => 'TEE-001',
            'variant_options' => [['name' => 'Color', 'values' => ['Red', 'Blue']]],
            'variants' => [
                ['selections' => ['Red'], 'sku' => 'TEE-RED', 'gtin' => '4006381333931', 'stock_quantity' => 1],
                ['selections' => ['Blue'], 'sku' => 'TEE-BLUE', 'gtin' => '4006381333931', 'stock_quantity' => 1],
            ],
        ])
        ->assertSessionHasErrors('variants');
});
