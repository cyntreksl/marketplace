<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promotion;
use Illuminate\Support\Facades\Storage;

test('the storefront home shares the ProDeals.lk identity', function () {
    config()->set('app.name', 'ProDeals.lk');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('name', 'ProDeals.lk')
            ->has('popularCategories')
            ->has('bestOffers')
            ->has('newArrivals')
            ->has('categories'));
});

test('runtime site images use the configured Cloudflare media domain', function () {
    config([
        'filesystems.media' => 'r2',
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'prodeals-media-production',
        'filesystems.disks.r2.endpoint' => 'https://account-id.r2.cloudflarestorage.com',
        'filesystems.disks.r2.url' => 'https://media.prodeals.lk',
    ]);
    Promotion::factory()->create([
        'placement' => 'hero',
        'image_path' => 'promotions/cloudflare-hero.jpg',
        'image_disk' => 'r2',
    ]);

    $home = $this->get(route('home'))->assertOk();

    expect($home->inertiaProps('promotions.hero.0.imageUrl'))
        ->toBe('https://media.prodeals.lk/promotions/cloudflare-hero.jpg')
        ->and($home->inertiaProps('promotions.secondary.0.imageUrl'))
        ->toBe('https://media.prodeals.lk/site/images/storefront/home-lifestyle.jpg')
        ->and(implode('', $home->inertiaProps('head')))
        ->toContain('https://media.prodeals.lk/site/prodeals-social-card.png')
        ->and($home->getContent())
        ->toContain('https://media.prodeals.lk/site/favicon.png')
        ->toContain('https://media.prodeals.lk/site/apple-touch-icon.png');

    $this->get(route('site.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('icons.0.src', 'https://media.prodeals.lk/site/apple-touch-icon.png');

    expect(view('vendor.mail.html.header', ['url' => 'https://prodeals.lk'])->render())
        ->toContain('https://media.prodeals.lk/site/prodeals-email-logo.png');

    Storage::forgetDisk('r2');
});

test('authenticated portals use distinct ProDeals theme colors', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));
    $portalLayout = file_get_contents(resource_path('js/components/portal-layout.tsx'));

    expect($stylesheet)
        ->toContain('.portal-theme-buyer {', '--primary: #ff6d00;')
        ->toContain('.portal-theme-seller {', '--primary: #b45309;')
        ->toContain('.portal-theme-admin {', '--primary: #171717;')
        ->toContain('.dark .portal-theme-buyer {')
        ->toContain('.dark .portal-theme-seller {', '--primary: #ff6d00;')
        ->toContain('.dark .portal-theme-admin {')
        ->and($portalLayout)
        ->toContain('className={`portal-theme-${portal}');
});

test('portal controls use semantic colors and consistent corner radii', function () {
    $portalLayout = file_get_contents(resource_path('js/components/portal-layout.tsx'));
    $sellerListings = file_get_contents(resource_path('js/pages/seller/listings/index.tsx'));

    expect($portalLayout)
        ->toContain('rounded-xl bg-slate-100')
        ->toContain('rounded-xl px-3 text-sm font-medium')
        ->and($sellerListings)
        ->toContain('rounded-xl bg-primary')
        ->not->toContain('rounded-full bg-amber-400');
});

test('seller product form keeps listing inputs conditional and chip based', function () {
    $sellerProductForm = file_get_contents(resource_path('js/components/seller-product-form.tsx'));

    expect($sellerProductForm)
        ->toContain('const PRODUCT_TYPE_OPTIONS')
        ->toContain('const CONDITION_OPTIONS')
        ->toContain('function SegmentedChoice')
        ->toContain('function ChipValueInput')
        ->toContain('New brand request')
        ->toContain('{!isVariantProduct ? (')
        ->toContain('{isVariantProduct && (')
        ->toContain('Aggregate Low Stock Alert')
        ->not->toContain('Values separated by commas');
});

test('the refreshed logo components point to the new asset variants', function () {
    $brandLogo = file_get_contents(resource_path('js/components/brand-logo.tsx'));
    $appHeader = file_get_contents(resource_path('js/components/app-header.tsx'));
    $appLogoIcon = file_get_contents(resource_path('js/components/app-logo-icon.tsx'));

    expect(file_exists(public_path('prodeals-logo-inverse.svg')))->toBeTrue()
        ->and(file_exists(public_path('prodeals-icon-inverse.svg')))->toBeTrue()
        ->and($brandLogo)
        ->toContain('/prodeals-logo.svg')
        ->toContain('/prodeals-logo-inverse.svg')
        ->not->toContain('brightness-0 invert')
        ->and($appHeader)
        ->toContain('<BrandLogo compact />')
        ->not->toContain('AppLogoIcon')
        ->and($appLogoIcon)
        ->toContain('/prodeals-icon-inverse.svg');
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
            'image_url' => null,
            'children' => [
                [...$firstChild->only(['id', 'name', 'slug']), 'image_url' => null],
                [...$laterChild->only(['id', 'name', 'slug']), 'image_url' => null],
            ],
        ],
        [
            ...$laterCategory->only(['id', 'name', 'slug']),
            'image_url' => null,
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
            'image_url' => null,
            'children' => [
                [...$listingCategory->only(['id', 'name', 'slug']), 'image_url' => null],
            ],
        ],
    ]);
});
