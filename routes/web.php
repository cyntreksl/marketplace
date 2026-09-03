<?php

use App\Http\Controllers\AdminBrandController;
use App\Http\Controllers\AdminCategoryBrowseController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminHomepageController;
use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\AdminPromotionController;
use App\Http\Controllers\AdminReturnController;
use App\Http\Controllers\AdminSellerController;
use App\Http\Controllers\AdminTaxonomyController;
use App\Http\Controllers\AuctionBidController;
use App\Http\Controllers\BrandDirectoryController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\BuyerReturnRequestController;
use App\Http\Controllers\BuyerReviewController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryLookupController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\ProductQuestionQueueController;
use App\Http\Controllers\ReturnEvidenceController;
use App\Http\Controllers\SellerListingController;
use App\Http\Controllers\SellerOnboardingController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerRegistrationController;
use App\Http\Controllers\SellerReturnRequestController;
use App\Http\Controllers\SellerWalletController;
use App\Http\Controllers\SeoDiscoveryController;
use App\Http\Controllers\SiteManifestController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/sitemap.xml', [SeoDiscoveryController::class, 'sitemap'])->name('sitemap.index');
Route::get('/sitemaps/static.xml', [SeoDiscoveryController::class, 'staticPages'])->name('sitemap.static');
Route::get('/sitemaps/categories.xml', [SeoDiscoveryController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemaps/brands.xml', [SeoDiscoveryController::class, 'brands'])->name('sitemap.brands');
Route::get('/sitemaps/products-{page}.xml', [SeoDiscoveryController::class, 'products'])->whereNumber('page')->name('sitemap.products');
Route::get('/robots.txt', [SeoDiscoveryController::class, 'robots'])->name('robots');
Route::get('/manifest.webmanifest', SiteManifestController::class)->name('site.manifest');
Route::inertia('/about', 'storefront/content/show', ['document' => 'about'])->name('about');
Route::inertia('/contact', 'storefront/content/show', ['document' => 'contact'])->name('contact');
Route::inertia('/help', 'storefront/content/show', ['document' => 'help'])->name('help');
Route::inertia('/faq', 'storefront/content/show', ['document' => 'faq'])->name('faq');
Route::inertia('/buying', 'storefront/content/show', ['document' => 'buying'])->name('buying');
Route::inertia('/selling', 'storefront/content/show', ['document' => 'selling'])->name('selling');
Route::inertia('/policies/shipping', 'storefront/content/show', ['document' => 'shipping'])->name('policies.shipping');
Route::inertia('/policies/returns-refunds', 'storefront/content/show', ['document' => 'returns'])->name('policies.returns');
Route::inertia('/legal/terms', 'storefront/content/show', ['document' => 'terms'])->name('legal.terms');
Route::inertia('/legal/privacy', 'storefront/content/show', ['document' => 'privacy'])->name('legal.privacy');
Route::inertia('/legal/cookies', 'storefront/content/show', ['document' => 'cookies'])->name('legal.cookies');
Route::inertia('/policies/sellers', 'storefront/content/show', ['document' => 'sellers'])->name('policies.sellers');
Route::inertia('/policies/prohibited-items', 'storefront/content/show', ['document' => 'prohibited'])->name('policies.prohibited');
Route::get('/listings', [StorefrontController::class, 'index'])->name('listings.index');
Route::get('/collections/{collection}', [StorefrontController::class, 'collection'])
    ->whereIn('collection', ['featured', 'deals', 'best-sellers', 'new-arrivals', 'clearance'])
    ->name('collections.show');
Route::get('/brands', BrandDirectoryController::class)->name('brands.index');
Route::get('/brands/{brand}', [StorefrontController::class, 'brand'])->name('brands.show');
Route::get('/listings/recent', [StorefrontController::class, 'recent'])->name('listings.recent');
Route::get('/listings/{listing}', [StorefrontController::class, 'show'])->name('listings.show');
Route::get('/compare', [ComparisonController::class, 'index'])->name('compare.index');
Route::get('/compare/listings', [ComparisonController::class, 'listings'])->name('compare.listings');
Route::get('/order-tracking', [OrderTrackingController::class, 'index'])->name('order-tracking.index');
Route::post('/order-tracking', [OrderTrackingController::class, 'store'])->middleware('throttle:order-tracking')->name('order-tracking.store');
Route::get('/categories/search', CategoryLookupController::class)
    ->middleware('throttle:category-lookups')
    ->name('categories.search');
Route::match(['get', 'post'], '/categories/suggest', [CategoryLookupController::class, 'suggest'])
    ->middleware('throttle:category-suggestions')
    ->name('categories.suggest');
Route::get('/categories/{category}', [StorefrontController::class, 'category'])->name('categories.show');
Route::get('/seller/register', [SellerRegistrationController::class, 'create'])->middleware('guest')->name('seller.register');

Route::post('/auctions/{auction}/bids', [AuctionBidController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:auction-bids'])
    ->name('auctions.bids.store');

Route::middleware('auth')->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/onboarding', [SellerOnboardingController::class, 'edit'])->name('onboarding.edit');
    Route::put('/onboarding', [SellerOnboardingController::class, 'update'])->name('onboarding.update');
    Route::get('/listings', [SellerListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/create', [SellerListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [SellerListingController::class, 'store'])->name('listings.store');
    Route::post('/listings/content-suggestions', [SellerListingController::class, 'contentSuggestions'])
        ->middleware('throttle:listing-content-suggestions')
        ->name('listings.content-suggestions');
    Route::get('/listings/{listing}', [SellerListingController::class, 'show'])->name('listings.show');
    Route::get('/listings/{listing}/edit', [SellerListingController::class, 'edit'])->name('listings.edit');
    Route::put('/listings/{listing}', [SellerListingController::class, 'update'])->name('listings.update');
    Route::delete('/listings/{listing}', [SellerListingController::class, 'destroy'])->name('listings.destroy');
    Route::post('/listings/submit', [SellerListingController::class, 'submit'])->name('listings.submit');
});

Route::middleware(['auth', 'verified'])->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{sellerOrder}/ready', [SellerOrderController::class, 'ready'])->name('orders.ready');
    Route::post('/orders/{sellerOrder}/delivered', [SellerOrderController::class, 'delivered'])->name('orders.delivered');
    Route::get('/returns', [SellerReturnRequestController::class, 'index'])->name('returns.index');
    Route::patch('/returns/{returnRequest}', [SellerReturnRequestController::class, 'update'])->name('returns.update');
    Route::get('/wallet', [SellerWalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/payouts', [SellerWalletController::class, 'store'])->name('wallet.payouts.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/buyer/orders', [BuyerDashboardController::class, 'index'])->name('buyer.orders.index');
    Route::post('/buyer/order-items/{orderItem}/review', [BuyerReviewController::class, 'store'])->name('buyer.reviews.store');
    Route::get('/buyer/returns', [BuyerReturnRequestController::class, 'index'])->name('buyer.returns.index');
    Route::post('/buyer/returns', [BuyerReturnRequestController::class, 'store'])->name('buyer.returns.store');
    Route::get('/wishlist', [WatchlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{listing:slug}', [WatchlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{listing:slug}', [WatchlistController::class, 'destroy'])->name('wishlist.destroy');
    Route::post('/listings/{listing:slug}/questions', [ProductQuestionController::class, 'store'])->name('listings.questions.store');
    Route::patch('/product-questions/{question}', [ProductQuestionController::class, 'update'])->name('product-questions.update');
    Route::get('/product-questions', ProductQuestionQueueController::class)->name('product-questions.index');
    Route::get('/returns/{returnRequest}/evidence/{evidence}', ReturnEvidenceController::class)
        ->whereNumber('evidence')
        ->name('returns.evidence.show');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/homepage', [AdminHomepageController::class, 'index'])->name('homepage.index');
    Route::put('/homepage/categories', [AdminHomepageController::class, 'updateCategories'])->name('homepage.categories.update');
    Route::patch('/homepage/listings/{listing}', [AdminHomepageController::class, 'updateListing'])->name('homepage.listings.update');
    Route::post('/homepage/promotions', [AdminPromotionController::class, 'store'])->name('homepage.promotions.store');
    Route::patch('/homepage/promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('homepage.promotions.update');
    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::post('/returns/{returnRequest}/refund-ready', [AdminReturnController::class, 'ready'])->name('returns.ready');
    Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'refund'])->name('returns.refund');
    Route::post('/returns/{returnRequest}/manual-refund', [AdminReturnController::class, 'manual'])->name('returns.manual');
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::patch('/sellers/{seller}', [AdminSellerController::class, 'update'])->name('sellers.update');
    Route::get('/listings', [AdminListingController::class, 'index'])->name('listings.index');
    Route::patch('/listings/{listing}', [AdminListingController::class, 'update'])->name('listings.update');
    Route::get('/catalog/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('/catalog/categories/children', [AdminCategoryBrowseController::class, 'children'])->name('categories.children');
    Route::get('/catalog/categories/search', [AdminCategoryBrowseController::class, 'search'])->name('categories.search');
    Route::get('/catalog/categories/{category}/context', [AdminCategoryBrowseController::class, 'context'])->whereNumber('category')->name('categories.context');
    Route::post('/catalog/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::patch('/catalog/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::post('/catalog/categories/{category}/image', [AdminCategoryController::class, 'storeImage'])->name('categories.image.store');
    Route::delete('/catalog/categories/{category}/image', [AdminCategoryController::class, 'destroyImage'])->name('categories.image.destroy');
    Route::post('/catalog/categories/{category}/banner-image', [AdminCategoryController::class, 'storeBannerImage'])->name('categories.banner_image.store');
    Route::delete('/catalog/categories/{category}/banner-image', [AdminCategoryController::class, 'destroyBannerImage'])->name('categories.banner_image.destroy');
    Route::patch('/catalog/categories/{category}/activation', [AdminCategoryController::class, 'updateActivation'])->name('categories.activation.update');
    Route::delete('/catalog/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/catalog/categories/{category}/restore', [AdminCategoryController::class, 'restore'])->name('categories.restore');
    Route::get('/catalog/brands', [AdminBrandController::class, 'index'])->name('brands.index');
    Route::post('/catalog/brands', [AdminBrandController::class, 'store'])->name('brands.store');
    Route::patch('/catalog/brands/{brand}', [AdminBrandController::class, 'update'])->name('brands.update');
    Route::delete('/catalog/brands/{brand}', [AdminBrandController::class, 'destroy'])->name('brands.destroy');
    Route::post('/catalog/brands/{brand}/restore', [AdminBrandController::class, 'restore'])->name('brands.restore');
    Route::get('/taxonomy', [AdminTaxonomyController::class, 'index'])->name('taxonomy.index');
    Route::post('/taxonomy', [AdminTaxonomyController::class, 'store'])->name('taxonomy.store');
    Route::post('/taxonomy/{taxonomy}/activate', [AdminTaxonomyController::class, 'activate'])->name('taxonomy.activate');
    Route::delete('/taxonomy/{taxonomy}', [AdminTaxonomyController::class, 'destroy'])->name('taxonomy.destroy');
});

require __DIR__.'/settings.php';
