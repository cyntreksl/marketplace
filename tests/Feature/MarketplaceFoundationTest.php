<?php

use App\Exceptions\InvalidAuctionBidException;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Listing;
use App\Models\User;
use App\Services\PlaceBidService;

test('guests can browse only approved listings', function () {
    $approved = Listing::factory()->create(['title' => 'Approved camera']);
    Listing::factory()->create(['title' => 'Hidden draft', 'status' => 'draft']);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/listings/index')
            ->has('listings.data', 1)
            ->where('listings.data.0.slug', $approved->slug));
});

test('a buyer can place a valid bid and cannot bid on their own auction', function () {
    $auction = Auction::factory()->create()->load('listing.sellerProfile.user');
    $buyer = User::factory()->create();

    $bid = app(PlaceBidService::class)->place($buyer, $auction->id, '12000.00');

    expect($bid)->toBeInstanceOf(Bid::class)
        ->and($auction->refresh()->current_price)->toEqual('10500.00');

    expect(fn () => app(PlaceBidService::class)->place($auction->listing->sellerProfile->user, $auction->id, '12500.00'))
        ->toThrow(InvalidAuctionBidException::class);
});
