<?php

use App\Models\Auction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Role;
use App\Models\SearchEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('guest storefront searches are normalized and tracked without an ip address', function () {
    Listing::factory()->create(['title' => 'Wireless Headphones', 'condition' => 'new']);

    $this->get(route('listings.index', [
        'search' => '  WIRELESS   Headphones ',
        'condition' => 'new',
    ]))->assertOk();

    $event = SearchEvent::query()->sole();

    expect($event)
        ->user_id->toBeNull()
        ->visitor_id->toBeString()
        ->term->toBe('WIRELESS Headphones')
        ->normalized_term->toBe('wireless headphones')
        ->result_count->toBe(0)
        ->context->toBe('listings')
        ->filters->toBe(['condition' => 'new'])
        ->and(Schema::hasColumn('search_events', 'ip_address'))->toBeFalse();
});

test('searches retain signed in user attribution', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('listings.index', ['search' => 'camera']))
        ->assertOk();

    expect(SearchEvent::query()->sole()->user_id)->toBe($buyer->id);
});

test('searches are deduplicated for thirty minutes and pagination is ignored', function () {
    $this->get(route('listings.index', ['search' => 'camera']))->assertOk();
    $this->get(route('listings.index', ['search' => ' Camera ']))->assertOk();
    $this->get(route('listings.index', ['search' => 'camera', 'page' => 2]))->assertOk();

    expect(SearchEvent::query()->count())->toBe(1);

    $this->travel(31)->minutes();
    $this->get(route('listings.index', ['search' => 'CAMERA']))->assertOk();

    expect(SearchEvent::query()->count())->toBe(2);
});

test('blank and invalid storefront searches are not tracked', function () {
    $this->get(route('listings.index', ['search' => '   ']))->assertOk();
    $this->get(route('listings.index', ['search' => str_repeat('x', 121)]))
        ->assertRedirect()
        ->assertSessionHasErrors('search');

    expect(SearchEvent::query()->count())->toBe(0);
});

test('searches are tracked across storefront browse contexts', function () {
    $category = Category::factory()->create(['slug' => 'electronics']);
    $brand = Brand::factory()->create(['slug' => 'acme']);
    $auctionListing = Listing::factory()->create([
        'title' => 'Auction Camera',
        'listing_type' => 'auction',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
    ]);
    Auction::factory()->create(['listing_id' => $auctionListing->id]);

    $requests = [
        [route('listings.index', ['search' => 'listing term']), 'listings'],
        [route('categories.show', [$category->slug, 'search' => 'category term']), 'category'],
        [route('brands.show', [$brand->slug, 'search' => 'brand term']), 'brand'],
        [route('collections.show', ['collection' => 'featured', 'search' => 'collection term']), 'collection'],
        [route('auctions.index', ['search' => 'auction term']), 'auctions'],
    ];

    foreach ($requests as [$url, $context]) {
        $this->get($url)->assertOk();

        expect(SearchEvent::query()->latest('id')->value('context'))->toBe($context);
    }

    expect(SearchEvent::query()->count())->toBe(5);
});

test('operational admins can view filtered search demand insights', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    SearchEvent::factory()->create([
        'term' => 'Camera',
        'normalized_term' => 'camera',
        'result_count' => 8,
        'searched_at' => now()->subDays(2),
    ]);
    SearchEvent::factory()->create([
        'term' => 'CAMERA',
        'normalized_term' => 'camera',
        'result_count' => 4,
        'searched_at' => now()->subDay(),
    ]);
    SearchEvent::factory()->create([
        'term' => 'Missing lens',
        'normalized_term' => 'missing lens',
        'result_count' => 0,
        'searched_at' => now()->subDays(2),
    ]);
    SearchEvent::factory()->create([
        'term' => 'Old search',
        'normalized_term' => 'old search',
        'result_count' => 0,
        'searched_at' => now()->subDays(8),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.search-insights.index', ['days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/search-insights/index')
            ->where('periodDays', 7)
            ->where('metrics.totalSearches', 3)
            ->where('metrics.uniqueTerms', 2)
            ->where('metrics.zeroResultRate', 33.3)
            ->where('topTerms.0.normalizedTerm', 'camera')
            ->where('topTerms.0.searchCount', 2)
            ->where('topTerms.0.averageResultCount', 6)
            ->where('topTerms.0.latestResultCount', 4)
            ->where('zeroResultTerms.0.normalizedTerm', 'missing lens')
            ->has('recentSearches.data', 3));
});

test('search insights default to thirty days and reject unsupported periods', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    $this->actingAs($admin)
        ->get(route('admin.search-insights.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('periodDays', 30));

    $this->actingAs($admin)
        ->get(route('admin.search-insights.index', ['days' => 14]))
        ->assertRedirect()
        ->assertSessionHasErrors('days');
});

test('non admin users cannot view search insights', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.search-insights.index'))
        ->assertForbidden();
});
