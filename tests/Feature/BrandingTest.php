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

test('the storefront shares an ordered two-level active category menu', function () {
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
    $laterChild = Category::factory()->create([
        'parent_id' => $firstCategory->id,
        'name' => 'Televisions',
        'slug' => 'electronics-televisions',
        'sort_order' => 20,
    ]);
    $firstChild = Category::factory()->create([
        'parent_id' => $firstCategory->id,
        'name' => 'Computers',
        'slug' => 'electronics-computers',
        'sort_order' => 10,
    ]);
    Category::factory()->create([
        'name' => 'Hidden Category',
        'slug' => 'hidden-category',
        'is_active' => false,
        'sort_order' => 0,
    ]);
    $deletedRoot = Category::factory()->create([
        'name' => 'Deleted Category',
        'slug' => 'deleted-category',
        'sort_order' => 0,
    ]);
    $deletedRoot->delete();
    Category::factory()->create([
        'parent_id' => $firstCategory->id,
        'name' => 'Inactive Child',
        'slug' => 'inactive-child',
        'is_active' => false,
        'sort_order' => 0,
    ]);
    $deletedChild = Category::factory()->create([
        'parent_id' => $firstCategory->id,
        'name' => 'Deleted Child',
        'slug' => 'deleted-child',
        'sort_order' => 0,
    ]);
    $deletedChild->delete();
    Category::factory()->create([
        'parent_id' => $firstChild->id,
        'name' => 'Laptop Accessories',
        'slug' => 'laptop-accessories',
        'sort_order' => 0,
    ]);

    $expectedCategories = [
        [
            ...$firstCategory->only(['id', 'name', 'slug']),
            'children' => [
                $firstChild->only(['id', 'name', 'slug']),
                $laterChild->only(['id', 'name', 'slug']),
            ],
        ],
        [
            ...$laterCategory->only(['id', 'name', 'slug']),
            'children' => [],
        ],
    ];

    $homeResponse = $this->get(route('home'))->assertOk();
    $indexResponse = $this->get(route('listings.index'))->assertOk();

    expect($homeResponse->inertiaProps('categories'))
        ->toBe($expectedCategories)
        ->and($indexResponse->inertiaProps('categories'))
        ->toBe($expectedCategories);
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
        [
            ...$topLevelCategory->only(['id', 'name', 'slug']),
            'children' => [
                $listingCategory->only(['id', 'name', 'slug']),
            ],
        ],
    ]);
});
