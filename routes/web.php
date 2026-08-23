<?php

use App\Http\Controllers\AuctionBidController;
use App\Http\Controllers\SellerListingController;
use App\Http\Controllers\SellerOnboardingController;
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
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
