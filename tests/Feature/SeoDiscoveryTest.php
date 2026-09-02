<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\User;

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
        ->assertInertia(fn ($page) => $page->where('listings.total', 0));

    $this->actingAs(User::factory()->create())
        ->post(route('cart.items.store'), ['listing_id' => $soldOut->id, 'quantity' => 1])
        ->assertNotFound();

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

    $this->get(route('categories.show', $category->slug))
        ->assertOk()
        ->assertSee('rel="canonical" href="'.route('categories.show', $category->slug).'"', false);
});

test('search filters and private pages are noindex while paginated clean landings self-canonicalize', function () {
    $category = Category::factory()->create(['slug' => 'phones']);
    $user = User::factory()->create();

    $this->get(route('categories.show', ['category' => $category->slug, 'search' => 'pixel']))
        ->assertOk()
        ->assertSee('name="robots" content="noindex,follow,max-image-preview:large"', false)
        ->assertSee('rel="canonical" href="'.route('categories.show', $category->slug).'"', false);

    $this->get(route('categories.show', ['category' => $category->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('rel="canonical" href="'.route('categories.show', ['category' => $category->slug, 'page' => 2]).'"', false);

    $this->actingAs($user)->get(route('cart.show'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex,follow,max-image-preview:large"', false);
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

test('robots permits crawling and declares the absolute sitemap URL', function () {
    $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee("User-agent: *\nAllow: /", false)
        ->assertSee('Sitemap: '.route('sitemap.index'), false);
});
