<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;

test('an approved seller can create a draft listing and submit it for moderation', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create(['commission_percentage' => 8]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'title' => 'Canon EOS R6',
            'description' => 'A well cared for full-frame camera body.',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'location' => 'Colombo',
            'stock_quantity' => 2,
            'price' => '325000.00',
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $listing = Listing::query()->sole();

    expect($listing->status)->toBe('draft')
        ->and($listing->commission_percentage)->toBe('8.00');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.submit'), ['listing_id' => $listing->id])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect($listing->refresh()->status)->toBe('pending_review');
});

test('an unapproved seller cannot submit a listing for moderation', function () {
    $seller = SellerProfile::factory()->create(['status' => 'pending_review']);
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => 'draft']);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.submit'), ['listing_id' => $listing->id])
        ->assertForbidden();

    expect($listing->refresh()->status)->toBe('draft');
});
