<?php

use App\Models\Auction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\User;
use App\Support\TrackingConsent;

function expectCanonicalLink(string $html, string $url): void
{
    expect($html)->toMatch('/<link\b(?=[^>]*rel="canonical")(?=[^>]*href="'.preg_quote($url, '/').'")[^>]*>/');
}

test('approved sold-out products remain indexable details but are absent from browse and purchase', function () {
    $soldOut = Listing::factory()->create([
        'title' => 'Sold out camera',
        'slug' => 'sold-out-camera',
        'stock_quantity' => 0,
        'reserved_quantity' => 0,
        'allow_backorders' => false,
    ]);
    ListingMedia::factory()->for($soldOut)->create();

    $this->get(route('listings.show', $soldOut->slug))
        ->assertOk()
        ->assertSee('https://schema.org/OutOfStock', false)
        ->assertSee('name="robots" content="index,follow,max-image-preview:large"', false);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('name="robots" content="index,follow,max-image-preview:large"', false)
        ->assertInertia(fn ($page) => $page->where('listings.total', 0));

    $this->get(route('categories.show', $soldOut->category->slug))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('name="robots" content="index,follow,max-image-preview:large"', false);

    $this->actingAs(User::factory()->create())
        ->post(route('cart.items.store'), ['listing_id' => $soldOut->id, 'quantity' => 1])
        ->assertRedirect()
        ->assertSessionHasErrors(['quantity' => 'This quantity is no longer available.']);

    $this->assertDatabaseMissing('cart_items', ['listing_id' => $soldOut->id]);

    $this->get(route('sitemap.products', 1))
        ->assertOk()
        ->assertSee(route('listings.show', $soldOut->slug), false);
});

test('clean catalog landings replace legacy single-dimension URLs and preserve pagination', function () {
    $category = Category::factory()->create(['slug' => 'phones']);
    $brand = Brand::factory()->create(['slug' => 'sony']);

    $this->get(route('listings.index', ['category' => $category->slug, 'page' => 3]))
        ->assertRedirect(route('categories.show', ['category' => $category->slug, 'page' => 3]))
        ->assertStatus(301);

    $this->get(route('listings.index', ['brand' => $brand->slug]))
        ->assertRedirect(route('brands.show', $brand->slug))
        ->assertStatus(301);

    $response = $this->get(route('categories.show', $category->slug));

    $response->assertOk();
    expectCanonicalLink($response->getContent(), route('categories.show', $category->slug));
});

test('search filters and private pages are noindex', function () {
    $category = Category::factory()->create(['slug' => 'phones']);
    $user = User::factory()->create();

    $filteredResponse = $this->get(route('categories.show', ['category' => $category->slug, 'search' => 'pixel']));

    $filteredResponse->assertOk()
        ->assertSee('name="robots" content="noindex,follow,max-image-preview:large"', false);
    expectCanonicalLink($filteredResponse->getContent(), route('categories.show', $category->slug));

    $this->actingAs($user)->get(route('cart.show'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex,follow,max-image-preview:large"', false);
});

test('paginated clean catalog landings self-canonicalize', function () {
    $category = Category::factory()->create(['slug' => 'phones']);

    $paginatedResponse = $this->get(route('categories.show', ['category' => $category->slug, 'page' => 2]));

    $paginatedResponse->assertOk();
    expectCanonicalLink($paginatedResponse->getContent(), route('categories.show', ['category' => $category->slug, 'page' => 2]));
});

test('sitemap index is chunked and excludes non-public products and variant URLs', function () {
    config(['marketplace.seo.sitemap_product_chunk_size' => 1]);
    $first = Listing::factory()->create(['slug' => 'first-product', 'stock_quantity' => 0]);
    $second = Listing::factory()->create(['slug' => 'second-product']);
    Listing::factory()->create(['slug' => 'draft-product', 'status' => 'draft']);

    $this->get(route('sitemap.index'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('sitemap.products', 1), false)
        ->assertSee(route('sitemap.products', 2), false);

    $this->get(route('sitemap.products', 1))
        ->assertOk()
        ->assertSee(route('listings.show', $first->slug), false)
        ->assertDontSee('?variant=', false)
        ->assertDontSee('draft-product', false);

    $this->get(route('sitemap.products', 2))
        ->assertOk()
        ->assertSee(route('listings.show', $second->slug), false);
});

test('taxonomy sitemap includes product categories and useful ancestors but excludes empty branches', function () {
    $parent = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Phones', 'slug' => 'phones']);
    $empty = Category::factory()->create(['name' => 'Empty', 'slug' => 'empty']);
    Listing::factory()->create(['category_id' => $child->id]);

    $response = $this->get(route('sitemap.categories'));

    $response->assertOk()
        ->assertSee(route('categories.show', $parent->slug), false)
        ->assertSee(route('categories.show', $child->slug), false)
        ->assertDontSee(route('categories.show', $empty->slug), false);
});

test('static sitemap includes product-bearing collections and excludes empty collections', function () {
    Listing::factory()->create([
        'is_best_offer' => true,
        'price' => '2000.00',
        'sale_price' => '1500.00',
    ]);

    $this->get(route('sitemap.static'))
        ->assertOk()
        ->assertSee(route('collections.show', 'deals'), false)
        ->assertDontSee(route('collections.show', 'clearance'), false);
});

test('product sitemap publishes high resolution R2 image entries', function () {
    $listing = Listing::factory()->create(['title' => 'Camera & Lens']);
    ListingMedia::factory()->for($listing)->create([
        'disk' => 'r2',
        'variants' => ['card_2x' => 'listings/camera-card-2x.webp'],
    ]);

    $this->get(route('sitemap.products', 1))
        ->assertOk()
        ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
        ->assertSee('<image:title>Camera &amp; Lens</image:title>', false)
        ->assertSee('camera-card-2x.webp', false);
});

test('canonical auctions page contains only live auctions and is indexable when populated', function () {
    $liveListing = Listing::factory()->create(['listing_type' => 'auction', 'title' => 'Live camera']);
    Auction::factory()->for($liveListing)->create();
    $endedListing = Listing::factory()->create(['listing_type' => 'auction', 'title' => 'Ended watch']);
    Auction::factory()->for($endedListing)->create(['ends_at' => now()->subMinute()]);

    $this->get(route('auctions.index'))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertInertia(fn ($page) => $page
            ->where('pageHeading', 'Online Auctions in Sri Lanka')
            ->where('listings.total', 1)
            ->where('listings.data.0.title', 'Live camera'));
});

test('GTM noscript fallback is emitted only for a valid granting consent cookie', function () {
    config(['services.google_tag_manager.container_id' => 'GTM-KTT94R7G']);

    $this->get(route('home'))->assertDontSee('googletagmanager.com/ns.html', false);

    $consent = urlencode(json_encode([
        'version' => 1,
        'analytics' => true,
        'marketing' => false,
        'decidedAt' => now()->toIso8601String(),
    ], JSON_THROW_ON_ERROR));

    $this->withUnencryptedCookie(TrackingConsent::COOKIE_NAME, $consent)
        ->get(route('home'))
        ->assertSee('googletagmanager.com/ns.html?id=GTM-KTT94R7G', false);
});

test('robots permits crawling and declares the absolute sitemap URL', function () {
    expect(public_path('robots.txt'))->not->toBeFile();

    $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee("User-agent: *\nAllow: /", false)
        ->assertSee('Sitemap: '.route('sitemap.index'), false);
});
