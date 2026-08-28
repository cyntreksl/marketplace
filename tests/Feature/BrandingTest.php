<?php

use App\Models\Category;
use App\Models\Listing;

test('the storefront home shares the ProDeals.lk identity', function () {
    config()->set('app.name', 'ProDeals.lk');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('name', 'ProDeals.lk')
            ->has('featuredListings.data')
            ->has('categories'));
});

test('the storefront shares every active top-level category in menu order', function () {
    $laterCategory = Category::factory()->create([
        'name' => 'Home & Garden',
        'slug' => 'home-garden',
        'sort_order' => 20,
    ]);
    $firstCategory = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'sort_order' => 10,
    ]);
    Category::factory()->create([
        'parent_id' => $firstCategory->id,
        'name' => 'Computers',
        'slug' => 'electronics-computers',
        'sort_order' => 1,
    ]);
    Category::factory()->create([
        'name' => 'Hidden Category',
        'slug' => 'hidden-category',
        'is_active' => false,
        'sort_order' => 0,
    ]);

    $response = $this->get(route('home'))->assertOk();

    expect($response->inertiaProps('categories'))->toBe([
        $firstCategory->only(['id', 'name', 'slug']),
        $laterCategory->only(['id', 'name', 'slug']),
    ]);
});

test('listing details retain the storefront category menu', function () {
    $topLevelCategory = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'sort_order' => 1,
    ]);
    $listingCategory = Category::factory()->create([
        'parent_id' => $topLevelCategory->id,
        'name' => 'Computers',
        'slug' => 'electronics-computers',
    ]);
    $listing = Listing::factory()->create([
        'category_id' => $listingCategory->id,
    ]);

    $response = $this->get(route('listings.show', $listing->slug))->assertOk();

    expect($response->inertiaProps('categories'))->toBe([
        $topLevelCategory->only(['id', 'name', 'slug']),
    ]);
});
