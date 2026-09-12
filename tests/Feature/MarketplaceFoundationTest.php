<?php

use App\Exceptions\InvalidAuctionBidException;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\SeoRedirect;
use App\Models\User;
use App\Services\PlaceBidService;

test('guests can browse only approved listings', function () {
    $approved = Listing::factory()->create(['title' => 'Approved camera']);
    Listing::factory()->create(['title' => 'Hidden draft', 'status' => 'draft']);
    $suspendedSeller = SellerProfile::factory()->create(['status' => 'suspended']);
    Listing::factory()->create([
        'seller_profile_id' => $suspendedSeller->id,
        'title' => 'Hidden suspended-seller camera',
    ]);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/listings/index')
            ->has('listings.data', 1)
            ->where('listings.data.0.slug', $approved->slug));
});

test('the storefront identifies seller accounts for portal links', function () {
    $seller = User::factory()->create();
    $seller->roles()->attach(Role::factory()->create([
        'name' => Role::BusinessSeller,
        'label' => 'Business Seller',
    ]));

    $this->actingAs($seller)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_seller', true));

    $buyer = User::factory()->create();
    $buyer->roles()->attach(Role::factory()->create([
        'name' => Role::Buyer,
        'label' => 'Buyer',
    ]));

    $this->actingAs($buyer)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_seller', false));
});

test('storefront listing media includes its configured public url', function () {
    config(['filesystems.disks.public.url' => 'https://media.prodeals.lk']);
    $listing = Listing::factory()->create();
    ListingMedia::factory()->create([
        'listing_id' => $listing->id,
        'disk' => 'public',
        'path' => 'listings/example.webp',
    ]);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/listings/show')
            ->where('listing.media.0.url', 'https://media.prodeals.lk/listings/example.webp'));
});

test('listing details include six related items and other products from the seller', function () {
    $listing = Listing::factory()->create();
    Listing::factory()->count(7)->create([
        'category_id' => $listing->category_id,
        'seller_profile_id' => $listing->seller_profile_id,
    ]);

    $response = $this->get(route('listings.show', $listing->slug))->assertOk();

    expect($response->inertiaProps('relatedListings'))->toHaveCount(6)
        ->and($response->inertiaProps('sellerListings'))->toHaveCount(6);
});

test('a buyer can place a valid bid and cannot bid on their own auction', function () {
    enableAuctions();
    $auction = Auction::factory()->create()->load('listing.sellerProfile.user');
    $buyer = User::factory()->create();

    $bid = app(PlaceBidService::class)->place($buyer, $auction->id, '12000.00');

    expect($bid)->toBeInstanceOf(Bid::class)
        ->and($auction->refresh()->current_price)->toEqual('12000.00');

    expect(fn () => app(PlaceBidService::class)->place($auction->listing->sellerProfile->user, $auction->id, '12500.00'))
        ->toThrow(InvalidAuctionBidException::class);
});

test('a verified account can submit seller onboarding details for review', function () {
    $user = User::factory()->create();
    Role::factory()->create(['name' => Role::IndividualSeller, 'label' => 'Individual Seller']);

    $this->actingAs($user)
        ->put(route('seller.onboarding.update'), [
            'seller_type' => 'individual',
            'store_name' => 'Colombo Devices',
            'phone' => '0771234567',
            'bank_account_name' => 'Test User',
            'bank_account_details' => 'Account 123',
            'accept_terms' => 'on',
        ])
        ->assertRedirect(route('seller.onboarding.edit', absolute: false));

    expect(SellerProfile::query()->where('user_id', $user->id)->firstOrFail()->status)->toBe('pending_review');

    $this->actingAs($user)
        ->put(route('seller.onboarding.update'), [
            'seller_type' => 'individual',
            'store_name' => 'Colombo Device Store',
            'phone' => '0771234567',
            'bank_account_name' => 'Test User',
            'bank_account_details' => 'Account 123',
            'accept_terms' => 'on',
        ])
        ->assertRedirect(route('seller.onboarding.edit', absolute: false));

    expect(SeoRedirect::query()->where('source_path', '/stores/colombo-devices-'.$user->id)->value('destination_path'))
        ->toBe('/stores/colombo-device-store-'.$user->id);
});
