<?php

use App\Models\Auction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use Inertia\Testing\AssertableInertia as Assert;

test('category pages receive their full ancestry and active immediate children', function () {
    $root = Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion', 'is_selectable' => false]);
    $parent = Category::factory()->create(['parent_id' => $root->id, 'name' => 'Clothing', 'slug' => 'fashion-clothing', 'is_selectable' => false]);
    $current = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Outerwear', 'slug' => 'fashion-clothing-outerwear', 'is_selectable' => false]);
    $activeChild = Category::factory()->create(['parent_id' => $current->id, 'name' => 'Coats', 'slug' => 'fashion-clothing-outerwear-coats', 'sort_order' => 2]);
    Category::factory()->create(['parent_id' => $activeChild->id, 'name' => 'Rain Coats', 'slug' => 'fashion-clothing-outerwear-coats-rain']);
    Category::factory()->create(['parent_id' => $current->id, 'name' => 'Inactive Jackets', 'slug' => 'inactive-jackets', 'is_active' => false]);

    $this->get(route('listings.index', ['category' => $current->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/listings/index')
            ->where('categoryContext.current.id', $current->id)
            ->where('categoryContext.current.name', 'Outerwear')
            ->has('categoryContext.ancestors', 2)
            ->where('categoryContext.ancestors.0.id', $root->id)
            ->where('categoryContext.ancestors.1.id', $parent->id)
            ->has('categoryContext.children', 1)
            ->where('categoryContext.children.0.id', $activeChild->id)
            ->where('categoryContext.children.0.has_children', true));
});

test('product pages receive a full root to leaf category trail', function () {
    $root = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    $parent = Category::factory()->create(['parent_id' => $root->id, 'name' => 'Computers', 'slug' => 'electronics-computers']);
    $leaf = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Laptops', 'slug' => 'electronics-computers-laptops']);
    $listing = Listing::factory()->create([
        'category_id' => $leaf->id,
        'model' => 'ThinkPad T14',
        'specifications' => ['Details' => '16GB RAM, 512GB SSD'],
    ]);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/listings/show')
            ->has('categoryTrail', 3)
            ->where('listing.model', 'ThinkPad T14')
            ->where('listing.specifications.Details', '16GB RAM, 512GB SSD')
            ->where('categoryTrail.0.id', $root->id)
            ->where('categoryTrail.1.id', $parent->id)
            ->where('categoryTrail.2.id', $leaf->id));
});

test('storefront browsing filters and sorts by the displayed effective price', function () {
    $seller = SellerProfile::factory()->create();
    $firstBrand = Brand::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);
    $secondBrand = Brand::factory()->create(['name' => 'Beta', 'slug' => 'beta']);

    $saleListing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'brand_id' => $firstBrand->id,
        'title' => 'Sale jacket',
        'condition' => 'new',
        'location' => 'Colombo Fort',
        'price' => 500,
        'sale_price' => 300,
        'created_at' => now()->subDays(3),
    ]);
    $regularListing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'brand_id' => $secondBrand->id,
        'title' => 'Regular jacket',
        'condition' => 'used',
        'location' => 'Kandy',
        'price' => 900,
        'created_at' => now()->subDays(2),
    ]);
    $auctionListing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'brand_id' => $firstBrand->id,
        'title' => 'Auction jacket',
        'condition' => 'refurbished',
        'listing_type' => 'auction',
        'location' => 'Galle',
        'price' => null,
        'created_at' => now()->subDay(),
    ]);
    Auction::factory()->create(['listing_id' => $auctionListing->id, 'current_price' => 600]);

    $ascendingIds = collect($this->get(route('listings.index', ['sort' => 'price_asc']))->inertiaProps('listings.data'))->pluck('id')->all();
    $descendingIds = collect($this->get(route('listings.index', ['sort' => 'price_desc']))->inertiaProps('listings.data'))->pluck('id')->all();

    expect($ascendingIds)->toBe([$saleListing->id, $auctionListing->id, $regularListing->id])
        ->and($descendingIds)->toBe([$regularListing->id, $auctionListing->id, $saleListing->id]);

    $brandIds = collect($this->get(route('listings.index', ['brand' => 'alpha']))->inertiaProps('listings.data'))->pluck('id')->all();
    $locationIds = collect($this->get(route('listings.index', [
        'location' => 'Colombo',
        'condition' => 'new',
        'listing_type' => 'buy_now',
    ]))->inertiaProps('listings.data'))->pluck('id')->all();
    $priceIds = collect($this->get(route('listings.index', ['min_price' => 400, 'max_price' => 700]))->inertiaProps('listings.data'))->pluck('id')->all();

    expect($brandIds)->toBe([$auctionListing->id, $saleListing->id])
        ->and($locationIds)->toBe([$saleListing->id])
        ->and($priceIds)->toBe([$auctionListing->id]);
});

test('storefront browse filters are validated', function (array $query, string $errorKey) {
    $this->get(route('listings.index', $query))
        ->assertRedirect()
        ->assertSessionHasErrors($errorKey);
})->with([
    'sort' => [['sort' => 'popular'], 'sort'],
    'condition' => [['condition' => 'broken'], 'condition'],
    'listing type' => [['listing_type' => 'rental'], 'listing_type'],
    'minimum price' => [['min_price' => -1], 'min_price'],
]);

test('pagination links retain the active storefront filters', function () {
    Listing::factory()->count(19)->create();

    $nextPageUrl = $this->get(route('listings.index', [
        'condition' => 'used',
        'sort' => 'price_asc',
    ]))->inertiaProps('listings.next_page_url');

    expect($nextPageUrl)
        ->toContain('condition=used')
        ->toContain('sort=price_asc')
        ->toContain('page=2');
});
