<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Models\ListingVariantOption;
use App\Models\ListingVariantOptionValue;
use App\Models\OrderItem;
use App\Models\Review;

/** @return array<int, array<string, mixed>> */
function productSeoGraphs(string $html): array
{
    preg_match_all('/<script data-inertia="json-ld-\d+" type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return collect($matches[1] ?? [])->map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR))->all();
}

test('simple products render safe merchant JSON-LD with Sri Lankan commerce values', function () {
    $category = Category::factory()->create([
        'name' => 'Mobile Phones',
        'slug' => 'mobile-phones',
        'google_product_category_id' => 267,
        'return_window_days' => 7,
    ]);
    $listing = Listing::factory()->create([
        'category_id' => $category->id,
        'title' => 'Pixel Phone </script><script>alert(1)</script>',
        'slug' => 'pixel-phone',
        'description' => '<p>A fast &amp; dependable phone.</p>',
        'sku' => 'PIXEL-001',
        'gtin' => '4006381333931',
        'mpn' => 'GA-PIXEL-001',
        'model' => 'Pixel Pro',
        'condition' => 'new',
        'price' => '250000.00',
        'sale_price' => '225000.00',
        'stock_quantity' => 2,
    ]);
    ListingMedia::factory()->for($listing)->create(['path' => 'listings/pixel.webp']);

    $response = $this->get(route('listings.show', $listing->slug));

    $response->assertOk()
        ->assertSee('lang="en-LK"', false)
        ->assertSee('property="og:locale" content="en_LK"', false)
        ->assertSee('property="product:price:currency" content="LKR"', false)
        ->assertDontSee('</script><script>alert(1)</script>', false);

    $graphs = productSeoGraphs($response->getContent());
    $product = collect($graphs)->firstWhere('@type', 'Product');

    expect($product)
        ->not->toBeNull()
        ->and($product['name'])->toBe($listing->title)
        ->and($product['description'])->toBe('A fast & dependable phone.')
        ->and($product['gtin13'])->toBe('4006381333931')
        ->and($product['mpn'])->toBe('GA-PIXEL-001')
        ->and($product['category'][0])->toBe('Mobile Phones')
        ->and($product['category'][1]['codeValue'])->toBe('267')
        ->and($product['offers']['price'])->toBe('225000.00')
        ->and($product['offers']['priceCurrency'])->toBe('LKR')
        ->and($product['offers']['availability'])->toBe('https://schema.org/InStock')
        ->and($product['offers']['hasMerchantReturnPolicy']['applicableCountry'])->toBe('LK')
        ->and($product['offers']['hasMerchantReturnPolicy']['merchantReturnDays'])->toBe(7)
        ->and($product['offers'])->not->toHaveKey('shippingDetails')
        ->and($product)->not->toHaveKey('additionalProperty')
        ->and($product)->not->toHaveKey('aggregateRating');
});

test('zero-day categories declare that returns are not permitted', function () {
    $category = Category::factory()->create(['return_window_days' => 0]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);
    ListingMedia::factory()->for($listing)->create();

    $product = collect(productSeoGraphs($this->get(route('listings.show', $listing->slug))->getContent()))->firstWhere('@type', 'Product');

    expect($product['offers']['hasMerchantReturnPolicy']['returnPolicyCategory'])
        ->toBe('https://schema.org/MerchantReturnNotPermitted');
});

test('aggregate ratings and shipping are emitted only from genuine and complete data', function () {
    config(['marketplace.seo.shipping' => [
        'rate' => '500.00',
        'handling_days_min' => 1,
        'handling_days_max' => 2,
        'transit_days_min' => 2,
        'transit_days_max' => 5,
    ]]);
    $listing = Listing::factory()->create();
    ListingMedia::factory()->for($listing)->create();
    $orderItem = OrderItem::factory()->create(['listing_id' => $listing->id]);
    Review::factory()->create([
        'order_item_id' => $orderItem->id,
        'seller_profile_id' => $listing->seller_profile_id,
        'rating' => 4,
    ]);

    $product = collect(productSeoGraphs($this->get(route('listings.show', $listing->slug))->getContent()))->firstWhere('@type', 'Product');

    expect($product['aggregateRating']['ratingValue'])->toBe(4)
        ->and($product['aggregateRating']['reviewCount'])->toBe(1)
        ->and($product['offers']['shippingDetails']['shippingDestination']['addressCountry'])->toBe('LK')
        ->and($product['offers']['shippingDetails']['shippingRate']['currency'])->toBe('LKR')
        ->and($product['offers']['shippingDetails']['deliveryTime']['transitTime']['maxValue'])->toBe(5);
});

test('variant pages expose ProductGroup relationships and stable direct variant URLs', function () {
    $listing = Listing::factory()->create([
        'product_type' => 'variant',
        'sku' => 'TEE-GROUP',
        'stock_quantity' => 3,
        'price' => '5000.00',
        'sale_price' => '4500.00',
    ]);
    ListingMedia::factory()->for($listing)->create(['path' => 'listings/tee.webp']);
    $option = ListingVariantOption::factory()->for($listing)->create(['name' => 'Color', 'position' => 0]);
    $red = ListingVariantOptionValue::factory()->for($option, 'option')->create(['value' => 'Red']);
    $blue = ListingVariantOptionValue::factory()->for($option, 'option')->create(['value' => 'Blue', 'position' => 1]);
    $redVariant = ListingVariant::factory()->for($listing)->create([
        'sku' => 'TEE-RED', 'gtin' => '96385074', 'selling_price' => '4500.00', 'market_price' => '5000.00', 'stock_quantity' => 3, 'position' => 0,
    ]);
    $blueVariant = ListingVariant::factory()->for($listing)->create([
        'sku' => 'TEE-BLUE', 'selling_price' => '4750.00', 'stock_quantity' => 0, 'position' => 1,
    ]);
    $redVariant->optionValues()->attach($red);
    $blueVariant->optionValues()->attach($blue);

    $response = $this->get(route('listings.show', ['listing' => $listing->slug, 'variant' => $blueVariant->id]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('selectedVariantId', $blueVariant->id)
        ->where('listing.variants.1.sku', 'TEE-BLUE'));

    $group = collect(productSeoGraphs($response->getContent()))->firstWhere('@type', 'ProductGroup');

    expect($group['productGroupID'])->toBe('TEE-GROUP')
        ->and($group['variesBy'])->toBe(['https://schema.org/color'])
        ->and($group['hasVariant'])->toHaveCount(2)
        ->and($group['hasVariant'][0]['color'])->toBe('Red')
        ->and($group['hasVariant'][0]['gtin8'])->toBe('96385074')
        ->and($group['hasVariant'][0]['offers']['url'])->toContain('?variant='.$redVariant->id)
        ->and($group['hasVariant'][1]['offers']['availability'])->toBe('https://schema.org/OutOfStock')
        ->and($response->getContent())->toContain('rel="canonical" href="'.route('listings.show', $listing->slug).'"');
});

test('auction pages emit breadcrumbs but no merchant Product graph', function () {
    $listing = Listing::factory()->create(['listing_type' => 'auction']);
    ListingMedia::factory()->for($listing)->create();

    $graphs = productSeoGraphs($this->get(route('listings.show', $listing->slug))->getContent());

    expect(collect($graphs)->pluck('@type')->all())->toBe(['BreadcrumbList']);
});
