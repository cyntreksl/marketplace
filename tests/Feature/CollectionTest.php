<?php

use App\Models\Collection;
use App\Models\Listing;

test('rule based collection pages filter listings exactly like the underlying flag', function () {
    $matching = Listing::factory()->create([
        'is_best_offer' => true,
        'price' => '10000.00',
        'sale_price' => '8000.00',
    ]);
    Listing::factory()->create(['is_best_offer' => false]);

    $response = $this->get('/collections/deals')->assertOk();

    $listingIds = collect($response->inertiaProps('listings.data'))->pluck('id');
    expect($listingIds)->toContain($matching->id)
        ->and($listingIds)->toHaveCount(1);
});

test('a manual collection shows only its assigned listings in curated order', function () {
    $collection = Collection::factory()->create(['name' => "Men's", 'slug' => 'mens-curated']);
    $first = Listing::factory()->create();
    $second = Listing::factory()->create();
    Listing::factory()->create();
    $collection->listings()->sync([
        $second->id => ['position' => 0],
        $first->id => ['position' => 1],
    ]);

    $response = $this->get('/collections/mens-curated')->assertOk();

    $listingIds = collect($response->inertiaProps('listings.data'))->pluck('id');
    expect($listingIds->all())->toBe([$second->id, $first->id]);
});

test('an inactive collection is not reachable on the storefront', function () {
    Collection::factory()->create(['slug' => 'inactive-one', 'is_active' => false]);

    $this->get('/collections/inactive-one')->assertNotFound();
});

test('an unknown collection slug is not found', function () {
    $this->get('/collections/does-not-exist')->assertNotFound();
});

test('the homepage exposes collection grid sections alongside existing merchandising props', function () {
    $gridCollection = Collection::factory()->create([
        'name' => 'Home Essentials',
        'show_on_homepage_grid' => true,
    ]);
    $gridCollection->listings()->attach(Listing::factory()->create(), ['position' => 0]);

    $response = $this->get('/')->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->has('popularCategories')
        ->has('bestOffers')
        ->has('newArrivals')
        ->has('categories')
        ->has('collectionSections'));

    $sections = collect($response->inertiaProps('collectionSections'));
    expect($sections->pluck('collection.name'))->toContain('Home Essentials');
});

test('a manual collection page uses its custom seo fields when set and falls back otherwise', function () {
    $withSeo = Collection::factory()->create([
        'name' => 'Kitchen',
        'slug' => 'kitchen',
        'seo_title' => 'Kitchen Deals - Custom Title',
        'seo_description' => 'A custom search result description.',
        'seo_intro' => 'A custom visible intro paragraph.',
    ]);
    $withSeo->listings()->attach(Listing::factory()->create(), ['position' => 0]);
    $withoutSeo = Collection::factory()->create(['name' => 'Garden', 'slug' => 'garden']);
    $withoutSeo->listings()->attach(Listing::factory()->create(), ['position' => 0]);

    $this->get('/collections/kitchen')
        ->assertOk()
        ->assertSee('<title data-inertia="title">Kitchen Deals - Custom Title</title>', false)
        ->assertSee('A custom search result description.', false);

    $this->get('/collections/garden')
        ->assertOk()
        ->assertSee('<title data-inertia="title">Garden in Sri Lanka - '.config('app.name').'</title>', false);
});

test('an active collection with matching listings is sitemapped with its own lastmod, inactive ones are not', function () {
    $collection = Collection::factory()->create(['name' => "Men's", 'slug' => 'mens-curated']);
    $collection->listings()->attach(Listing::factory()->create(), ['position' => 0]);
    $collection->touch();

    $inactive = Collection::factory()->create(['name' => 'Archived Picks', 'slug' => 'archived-picks', 'is_active' => false]);
    $inactive->listings()->attach(Listing::factory()->create(), ['position' => 0]);

    $empty = Collection::factory()->create(['name' => 'Empty Picks', 'slug' => 'empty-picks']);

    $response = $this->get(route('sitemap.static'))
        ->assertOk()
        ->assertSee(route('collections.show', 'mens-curated'), false)
        ->assertDontSee(route('collections.show', 'archived-picks'), false)
        ->assertDontSee(route('collections.show', 'empty-picks'), false);

    expect($response->getContent())->toContain('<lastmod>'.$collection->refresh()->updated_at->format('c').'</lastmod>');
});

test('homepage collection sections include up to 14 listings', function () {
    $collection = Collection::factory()->create([
        'name' => "Men's Collection",
        'show_on_homepage_grid' => true,
    ]);
    Listing::factory()->count(18)->create()->each(
        fn (Listing $listing, int $index) => $collection->listings()->attach($listing, ['position' => $index]),
    );

    $sections = collect($this->get('/')->assertOk()->inertiaProps('collectionSections'));

    expect($sections->firstWhere('collection.name', "Men's Collection")['listings'])->toHaveCount(14);
});

test('a collection page uses its own artwork as the open graph image', function () {
    $collection = Collection::factory()->create([
        'slug' => 'mens-curated',
        'image_path' => 'collections/1/tile/mens.webp',
        'image_disk' => 'public',
        'banner_image_path' => null,
    ]);

    $response = $this->get('/collections/mens-curated')->assertOk();

    expect($response->inertiaProps('seo.openGraph.image'))->toBe($collection->imageUrl())
        ->and(implode('', $response->inertiaProps('head')))
        ->toContain('property="og:image" content="'.e($collection->imageUrl()).'"')
        ->not->toContain('prodeals-social-card.png');
});

test('a collection page without artwork omits the open graph image', function () {
    Collection::factory()->create(['slug' => 'plain', 'image_path' => null, 'banner_image_path' => null, 'vertical_image_path' => null]);

    $head = implode('', $this->get('/collections/plain')->assertOk()->inertiaProps('head'));

    expect($head)->not->toContain('og:image')
        ->toContain('name="twitter:card" content="summary"');
});
