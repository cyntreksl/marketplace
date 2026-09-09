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
        'placement' => 'secondary',
        'image_path' => 'promotions/cloudflare-secondary.jpg',
        'image_disk' => 'r2',
    ]);

    $home = $this->get(route('home'))->assertOk();

    expect($home->inertiaProps('promotions.hero.0.imageUrl'))
        ->toBe('https://media.prodeals.lk/site/images/storefront/home-deals-banner.png?v='.hash_file('sha256', public_path('images/storefront/home-deals-banner.png')))
        ->and($home->inertiaProps('promotions.secondary.0.imageUrl'))
        ->toBe('https://media.prodeals.lk/promotions/cloudflare-secondary.jpg')
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

test('storefront listing pages share the category listing card', function () {
    $listingCard = file_get_contents(resource_path('js/components/listing-card.tsx'));
    $productGrid = file_get_contents(resource_path('js/components/storefront-product-grid.tsx'));
    $categoryListings = file_get_contents(resource_path('js/pages/storefront/listings/index.tsx'));
    $home = file_get_contents(resource_path('js/pages/storefront/home.tsx'));
    $listingShow = file_get_contents(resource_path('js/pages/storefront/listings/show.tsx'));

    expect($listingCard)
        ->toContain('export function ListingCard({ listing }: { listing: StorefrontListing })')
        ->toContain('src={image.cardUrl}', 'alt={listing.title}', '{listing.title}')
        ->toContain('href={detailHref}', 'wholesale: true', '<ListingPrice value={listing.effectivePrice} />')
        ->toContain('mr-1 text-sm font-semibold tracking-normal')
        ->toContain('Save {formatPrice(savings.toString())}', 'Contact seller', 'Out of stock', 'Auction')
        ->toContain("listing.stockStatus === 'out_of_stock'", 'absolute top-3 left-3 z-10 rounded-full bg-slate-900')
        ->toContain('Number.isFinite(savings) && savings > 0', "listing.listingType === 'buy_now'")
        ->not->toContain('line-through')
        ->not->toContain('<Form', '<Button', '<button', 'addCartItem', 'toast')
        ->not->toContain('Official warranty', 'Islandwide delivery', 'ratingAverage', 'listingBadge')
        ->and($productGrid)
        ->toContain("import { ListingCard } from '@/components/listing-card';")
        ->toContain('<ListingCard key={listing.id} listing={listing} />')
        ->and($categoryListings)
        ->toContain("import { StorefrontProductGrid } from '@/components/storefront-product-grid';")
        ->toContain('<StorefrontProductGrid')
        ->toContain('className="lg:grid-cols-3 xl:grid-cols-6"')
        ->not->toContain('function ListingTile')
        ->and($home)
        ->toContain("import { ListingCard } from '@/components/listing-card';")
        ->toContain('<ListingCard listing={listing} />')
        ->toContain('w-[calc((100%-0.75rem)/2)]', 'lg:w-[calc((100%-3.75rem)/6)]')
        ->and($listingShow)
        ->toContain('storefront-container', 'Related items', 'More from')
        ->toContain('grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6')
        ->not->toContain('You May Also Like', 'max-w-[96rem]')
        ->and(file_get_contents(resource_path('js/pages/storefront/watchlist/index.tsx')))
        ->toContain('grid-cols-2', 'lg:grid-cols-6');
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
    $accessories = Category::factory()->create([
        'parent_id' => $firstChild->id,
        'name' => 'Laptop Accessories',
        'slug' => 'laptop-accessories',
        'sort_order' => 0,
    ]);
    Listing::factory()->create(['category_id' => $accessories->id]);
    Listing::factory()->create(['category_id' => $laterChild->id]);
    Listing::factory()->create(['category_id' => $laterCategory->id]);

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

test('informational pages share categories for the compact storefront header', function (string $routeName) {
    $category = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    $child = Category::factory()->create(['parent_id' => $category->id]);
    Listing::factory()->create(['category_id' => $child->id]);
    Category::factory()->create(['is_active' => false]);

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/content/show')
            ->has('categories', 1)
            ->where('categories.0.slug', $category->slug)
            ->where('categories.0.children.0.slug', $child->slug));
})->with(['about', 'help', 'policies.shipping', 'legal.terms']);

test('storefront and checkout pages use the home page gutters', function (string $component) {
    $source = file_get_contents(resource_path('js/'.$component.'.tsx'));

    expect($source)
        ->toContain('storefront-container')
        ->not->toContain('max-w-[90rem]', 'max-w-[96rem]', 'max-w-6xl');
})->with([
    'pages/storefront/home',
    'pages/storefront/listings/index',
    'pages/storefront/listings/show',
    'pages/storefront/brands',
    'pages/storefront/compare',
    'pages/storefront/watchlist/index',
    'pages/storefront/content/show',
    'pages/storefront/order-tracking',
    'pages/buyer/cart',
    'pages/buyer/checkout',
    'pages/buyer/payment',
    'pages/buyer/review',
    'pages/buyer/thank-you',
    'components/storefront-layout',
    'components/storefront-footer',
]);

test('storefront gutters retain the home page responsive dimensions', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($stylesheet)->toContain('@utility storefront-container {', 'mx-auto w-full max-w-[82rem] px-4', '@variant sm', '@apply px-6');
});

test('product details and checkout keep supporting text readable', function (string $component) {
    $source = file_get_contents(resource_path('js/'.$component.'.tsx'));

    expect($source)
        ->not->toContain('text-[10px]', 'text-[11px]', 'text-xs')
        ->toContain('text-sm');
})->with([
    'pages/storefront/listings/show',
    'pages/buyer/checkout',
    'pages/buyer/payment',
    'pages/buyer/review',
    'pages/buyer/thank-you',
    'components/cart-contents',
    'components/checkout-progress',
]);

test('payment uses card-first tiles and keeps full delivery details with the order summary', function () {
    $source = file_get_contents(resource_path('js/pages/buyer/payment.tsx'));

    expect($source)
        ->toContain("cart.paymentMethods.includes('stripe')")
        ->toContain("? 'stripe'")
        ->toContain('className="peer sr-only"')
        ->toContain('peer-checked:border-[#ff5a00]')
        ->toContain('Delivery address', 'Change details')
        ->toContain(
            'shippingAddress.recipient_name',
            'shippingAddress.address_line_one',
            'shippingAddress.address_line_two',
            'shippingAddress.city',
            'shippingAddress.postal_code',
            'shippingAddress.phone',
        );
});
