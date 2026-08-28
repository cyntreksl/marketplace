<?php

use App\Http\Controllers\AdminBrandController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\AdminSellerController;
use App\Http\Controllers\AdminTaxonomyController;
use App\Http\Controllers\AuctionBidController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryLookupController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SellerListingController;
use App\Http\Controllers\SellerOnboardingController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerWalletController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\VendorRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
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
Route::get('/listings/{listing}', [StorefrontController::class, 'show'])->name('listings.show');
Route::get('/categories/search', CategoryLookupController::class)
    ->middleware('throttle:category-lookups')
    ->name('categories.search');
Route::get('/vendor/register', [VendorRegistrationController::class, 'create'])->middleware('guest')->name('vendor.register');

Route::post('/auctions/{auction}/bids', [AuctionBidController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:auction-bids'])
    ->name('auctions.bids.store');

Route::middleware('auth')->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/onboarding', [SellerOnboardingController::class, 'edit'])->name('onboarding.edit');
    Route::put('/onboarding', [SellerOnboardingController::class, 'update'])->name('onboarding.update');
    Route::get('/listings', [SellerListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/create', [SellerListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [SellerListingController::class, 'store'])->name('listings.store');
    Route::get('/listings/{listing}/edit', [SellerListingController::class, 'edit'])->name('listings.edit');
    Route::put('/listings/{listing}', [SellerListingController::class, 'update'])->name('listings.update');
    Route::post('/listings/submit', [SellerListingController::class, 'submit'])->name('listings.submit');
});

Route::middleware(['auth', 'verified'])->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{sellerOrder}/ready', [SellerOrderController::class, 'ready'])->name('orders.ready');
    Route::get('/wallet', [SellerWalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/payouts', [SellerWalletController::class, 'store'])->name('wallet.payouts.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/buyer/orders', [BuyerDashboardController::class, 'index'])->name('buyer.orders.index');
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::patch('/sellers/{seller}', [AdminSellerController::class, 'update'])->name('sellers.update');
    Route::get('/listings', [AdminListingController::class, 'index'])->name('listings.index');
    Route::patch('/listings/{listing}', [AdminListingController::class, 'update'])->name('listings.update');
    Route::get('/catalog/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/catalog/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::patch('/catalog/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
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
