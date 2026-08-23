<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\AdminSellerController;
use App\Http\Controllers\AuctionBidController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SellerListingController;
use App\Http\Controllers\SellerOnboardingController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerWalletController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/listings', [StorefrontController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing}', [StorefrontController::class, 'show'])->name('listings.show');

Route::post('/auctions/{auction}/bids', [AuctionBidController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:auction-bids'])
    ->name('auctions.bids.store');

Route::middleware(['auth', 'verified'])->prefix('seller')->name('seller.')->group(function (): void {
    Route::get('/onboarding', [SellerOnboardingController::class, 'edit'])->name('onboarding.edit');
    Route::put('/onboarding', [SellerOnboardingController::class, 'update'])->name('onboarding.update');
    Route::get('/listings', [SellerListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/create', [SellerListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [SellerListingController::class, 'store'])->name('listings.store');
    Route::post('/listings/submit', [SellerListingController::class, 'submit'])->name('listings.submit');
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
});

require __DIR__.'/settings.php';
