<?php

use App\Http\Controllers\AdminAuctionController;
use App\Http\Controllers\AdminAuctionSettingsController;
use App\Http\Controllers\AdminBrandController;
use App\Http\Controllers\AdminCategoryBrowseController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminHomepageController;
use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\AdminPromotionController;
use App\Http\Controllers\AdminReturnController;
use App\Http\Controllers\AdminSearchInsightsController;
use App\Http\Controllers\AdminSellerController;
use App\Http\Controllers\AdminTaxonomyController;
use App\Http\Controllers\AuctionBidController;
use App\Http\Controllers\BrandDirectoryController;
use App\Http\Controllers\BuyerAddressController;
use App\Http\Controllers\BuyerAuctionOfferController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\BuyerFeedbackController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\BuyerPaymentController;
use App\Http\Controllers\BuyerReturnRequestController;
use App\Http\Controllers\BuyerReviewController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryLookupController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutPaymentController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\MerchantFeedController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\ProductQuestionQueueController;
use App\Http\Controllers\ReturnEvidenceController;
use App\Http\Controllers\SellerAuctionController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerListingController;
use App\Http\Controllers\SellerOnboardingController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerRegistrationController;
use App\Http\Controllers\SellerReturnRequestController;
use App\Http\Controllers\SellerStoreController;
use App\Http\Controllers\SellerWalletController;
use App\Http\Controllers\SellerWholesaleController;
use App\Http\Controllers\SeoDiscoveryController;
use App\Http\Controllers\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\SiteManifestController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::middleware('cache.headers:public;no_cache;must_revalidate;etag')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->group(function (): void {
        Route::get('/sitemap.xml', [SeoDiscoveryController::class, 'sitemap'])->name('sitemap.index');
        Route::get('/sitemaps/static.xml', [SeoDiscoveryController::class, 'staticPages'])->name('sitemap.static');
        Route::get('/sitemaps/categories.xml', [SeoDiscoveryController::class, 'categories'])->name('sitemap.categories');
        Route::get('/sitemaps/brands.xml', [SeoDiscoveryController::class, 'brands'])->name('sitemap.brands');
        Route::get('/sitemaps/products-{page}.xml', [SeoDiscoveryController::class, 'products'])->whereNumber('page')->name('sitemap.products');
        Route::get('/sitemaps/stores.xml', [SeoDiscoveryController::class, 'stores'])->name('sitemap.stores');
        Route::get('/robots.txt', [SeoDiscoveryController::class, 'robots'])->name('robots');
        Route::get('/feeds/google-merchant.xml', MerchantFeedController::class)->name('feeds.google_merchant');
    });
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
Route::get('/wholesale', [StorefrontController::class, 'wholesale'])->name('wholesale.index');
Route::get('/auctions', [StorefrontController::class, 'auctions'])->name('auctions.index');
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
    ->middleware(['auth', 'throttle:auction-bids'])
    ->name('auctions.bids.store');

Route::middleware('auth')->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/onboarding', [SellerOnboardingController::class, 'edit'])->name('onboarding.edit');
    Route::put('/onboarding', [SellerOnboardingController::class, 'update'])->name('onboarding.update');
    Route::get('/wholesale', [SellerWholesaleController::class, 'index'])->name('wholesale.index');
    Route::get('/wholesale/create', [SellerWholesaleController::class, 'create'])->name('wholesale.create');
    Route::get('/auctions', [SellerAuctionController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/create', [SellerAuctionController::class, 'create'])->name('auctions.create');
    Route::post('/auctions', [SellerAuctionController::class, 'store'])->name('auctions.store');
    Route::get('/auctions/{auction}', [SellerAuctionController::class, 'show'])->whereNumber('auction')->name('auctions.show');
    Route::get('/auctions/{auction}/edit', [SellerAuctionController::class, 'edit'])->whereNumber('auction')->name('auctions.edit');
    Route::put('/auctions/{auction}', [SellerAuctionController::class, 'update'])->whereNumber('auction')->name('auctions.update');
    Route::delete('/auctions/{auction}', [SellerAuctionController::class, 'destroy'])->whereNumber('auction')->name('auctions.destroy');
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

Route::middleware('auth')->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/', SellerDashboardController::class)->name('dashboard');
    Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{sellerOrder:number}', [SellerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{sellerOrder:number}/processing', [SellerOrderController::class, 'processing'])->name('orders.processing');
    Route::post('/orders/{sellerOrder:number}/ready', [SellerOrderController::class, 'ready'])->name('orders.ready');
    Route::post('/orders/{sellerOrder:number}/shipped', [SellerOrderController::class, 'shipped'])->name('orders.shipped');
    Route::post('/orders/{sellerOrder:number}/delivered', [SellerOrderController::class, 'delivered'])->name('orders.delivered');
    Route::get('/returns', [SellerReturnRequestController::class, 'index'])->name('returns.index');
    Route::patch('/returns/{returnRequest}', [SellerReturnRequestController::class, 'update'])->name('returns.update');
    Route::get('/wallet', [SellerWalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/payouts', [SellerWalletController::class, 'store'])->name('wallet.payouts.store');
});

Route::get('/cart', [CartController::class, 'show'])->block()->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->block()->name('cart.items.store');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->block()->name('cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->block()->name('cart.items.destroy');
Route::post('/webhooks/stripe', [CheckoutPaymentController::class, 'webhook'])->name('webhooks.stripe');

Route::middleware('auth')->group(function () {
    Route::post('/checkout/orders/{customerOrder:number}/pay', [CheckoutPaymentController::class, 'retry'])->name('checkout.card.retry');
    Route::get('/checkout/orders/{customerOrder:number}/return', [CheckoutPaymentController::class, 'returned'])->name('checkout.card.return');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/payment', [CheckoutController::class, 'showPayment'])->name('checkout.payment.show');
    Route::post('/checkout/payment', [CheckoutController::class, 'storePayment'])->name('checkout.payment.store');
    Route::get('/checkout/review', [CheckoutController::class, 'showReview'])->name('checkout.review.show');
    Route::post('/checkout/review', [CheckoutController::class, 'placeOrder'])->block(30, 10)->name('checkout.review.store');
    Route::get('/checkout/thank-you/{customerOrder:number}', [CheckoutController::class, 'thankYou'])->name('checkout.thank_you.show');
    Route::get('/buyer', [BuyerDashboardController::class, 'index'])->name('buyer.dashboard');
    Route::get('/buyer/orders', [BuyerOrderController::class, 'index'])->name('buyer.orders.index');
    Route::get('/buyer/auction-offers', [BuyerAuctionOfferController::class, 'index'])->name('buyer.auction-offers.index');
    Route::get('/buyer/auction-offers/{auctionOffer}', [BuyerAuctionOfferController::class, 'show'])->whereNumber('auctionOffer')->name('buyer.auction-offers.show');
    Route::post('/buyer/auction-offers/{auctionOffer}/accept', [BuyerAuctionOfferController::class, 'accept'])->whereNumber('auctionOffer')->block(30, 10)->name('buyer.auction-offers.accept');
    Route::get('/buyer/orders/{customerOrder:number}', [BuyerOrderController::class, 'show'])->name('buyer.orders.show');
    Route::get('/buyer/payments', [BuyerPaymentController::class, 'index'])->name('buyer.payments.index');
    Route::get('/buyer/feedback', [BuyerFeedbackController::class, 'index'])->name('buyer.feedback.index');
    Route::post('/buyer/order-items/{orderItem}/review', [BuyerReviewController::class, 'store'])->name('buyer.reviews.store');
    Route::get('/buyer/returns', [BuyerReturnRequestController::class, 'index'])->name('buyer.returns.index');
    Route::post('/buyer/returns', [BuyerReturnRequestController::class, 'store'])->name('buyer.returns.store');
    Route::get('/buyer/addresses', [BuyerAddressController::class, 'index'])->name('buyer.addresses.index');
    Route::post('/buyer/addresses', [BuyerAddressController::class, 'store'])->name('buyer.addresses.store');
    Route::patch('/buyer/addresses/{buyerAddress}', [BuyerAddressController::class, 'update'])->name('buyer.addresses.update');
    Route::delete('/buyer/addresses/{buyerAddress}', [BuyerAddressController::class, 'destroy'])->name('buyer.addresses.destroy');
    Route::patch('/buyer/addresses/{buyerAddress}/default', [BuyerAddressController::class, 'setDefault'])->name('buyer.addresses.default');
    Route::get('/buyer/settings/profile', [SettingsProfileController::class, 'edit'])->name('buyer.settings.profile.edit');
    Route::patch('/buyer/settings/profile', [SettingsProfileController::class, 'update'])->name('buyer.settings.profile.update');
    Route::delete('/buyer/settings/profile', [SettingsProfileController::class, 'destroy'])->name('buyer.settings.profile.destroy');
    Route::get('/buyer/settings/security', [SettingsSecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('buyer.settings.security.edit');
    Route::put('/buyer/settings/password', [SettingsSecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('buyer.settings.password.update');
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

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/search-insights', AdminSearchInsightsController::class)->name('search-insights.index');
    Route::get('/auctions', [AdminAuctionController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/settings', [AdminAuctionSettingsController::class, 'index'])->name('auctions.settings.index');
    Route::put('/auctions/settings', [AdminAuctionSettingsController::class, 'update'])->name('auctions.settings.update');
    Route::get('/auctions/{auction}', [AdminAuctionController::class, 'show'])->whereNumber('auction')->name('auctions.show');
    Route::post('/auctions/{auction}/cancel', [AdminAuctionController::class, 'cancel'])->whereNumber('auction')->name('auctions.cancel');
    Route::get('/homepage', [AdminHomepageController::class, 'index'])->name('homepage.index');
    Route::put('/homepage/categories', [AdminHomepageController::class, 'updateCategories'])->name('homepage.categories.update');
    Route::patch('/homepage/listings/{listing}', [AdminHomepageController::class, 'updateListing'])->name('homepage.listings.update');
    Route::post('/homepage/promotions', [AdminPromotionController::class, 'store'])->name('homepage.promotions.store');
    Route::patch('/homepage/promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('homepage.promotions.update');
    Route::delete('/homepage/promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('homepage.promotions.destroy');
    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::post('/returns/{returnRequest}/refund-ready', [AdminReturnController::class, 'ready'])->name('returns.ready');
    Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'refund'])->name('returns.refund');
    Route::post('/returns/{returnRequest}/manual-refund', [AdminReturnController::class, 'manual'])->name('returns.manual');
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::patch('/sellers/{seller}', [AdminSellerController::class, 'update'])->name('sellers.update');
    Route::get('/listings', [AdminListingController::class, 'index'])->name('listings.index');
    Route::get('/products', [AdminListingController::class, 'products'])->name('products.index');
    Route::get('/listings/{listing}/edit', [AdminListingController::class, 'edit'])->name('listings.edit');
    Route::get('/listings/{listing}', [AdminListingController::class, 'show'])->name('listings.show');
    Route::put('/listings/{listing}', [AdminListingController::class, 'updateDetails'])->name('listings.details.update');
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

Route::get('/stores/{seller:slug}', [SellerStoreController::class, 'show'])->name('stores.show');
Route::middleware('auth')->group(function (): void {
    Route::get('/seller/store', [SellerStoreController::class, 'edit'])->name('seller.store.edit');
    Route::put('/seller/store', [SellerStoreController::class, 'update'])->name('seller.store.update');
});
