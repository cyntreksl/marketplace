<?php

use App\AuctionOfferStatus;
use App\AuctionStatus;
use App\AuctionType;
use App\Exceptions\InvalidAuctionBidException;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\MarketplaceSetting;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\AuctionOfferNotification;
use App\Services\AuctionLifecycleService;
use App\Services\AuctionService;
use App\Services\MarketplaceModerationService;
use App\Services\PlaceBidService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

test('auction discovery and bids are blocked while the master flag is disabled', function () {
    $auction = Auction::factory()->create();
    $buyer = User::factory()->create();

    $this->get(route('auctions.index'))->assertNotFound();

    expect(fn () => app(PlaceBidService::class)->place($buyer, $auction->id, '11000'))
        ->toThrow(InvalidAuctionBidException::class, 'disabled');
});

test('seller schedules an auction and reserves its lot inventory', function () {
    enableAuctions();
    $listing = Listing::factory()->create(['stock_quantity' => 8, 'reserved_quantity' => 1]);
    $seller = $listing->sellerProfile->user;

    $auction = app(AuctionService::class)->create($seller, [
        'listing_id' => $listing->id,
        'type' => AuctionType::Normal->value,
        'quantity' => 3,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDays(2)->toDateTimeString(),
    ]);

    expect($auction->status)->toBe(AuctionStatus::Scheduled)
        ->and($auction->inventory_reserved_at)->not->toBeNull()
        ->and($listing->refresh()->reserved_quantity)->toBe(4);

    expect(fn () => app(AuctionService::class)->create($seller, [
        'listing_id' => $listing->id,
        'type' => AuctionType::Blind->value,
        'quantity' => 1,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDays(2)->toDateTimeString(),
    ]))->toThrow(ValidationException::class);
});

test('disabled auction types block creation and bidding independently of the master flag', function () {
    enableAuctions([AuctionType::Normal->value]);
    $blindAuction = Auction::factory()->create(['type' => AuctionType::Blind]);

    expect(fn () => app(PlaceBidService::class)->place(User::factory()->create(), $blindAuction->id, '11000'))
        ->toThrow(InvalidAuctionBidException::class, 'disabled');

    expect(fn () => app(AuctionService::class)->create($blindAuction->listing->sellerProfile->user, [
        'listing_id' => Listing::factory()->for($blindAuction->listing->sellerProfile)->create()->id,
        'type' => AuctionType::Blind->value,
        'quantity' => 1,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ]))->toThrow(ValidationException::class, 'disabled');
});

test('moderation approval schedules a combined auction draft and reserves inventory', function () {
    enableAuctions();
    $listing = Listing::factory()->create([
        'status' => 'pending_review',
        'approved_at' => null,
        'stock_quantity' => 4,
    ]);
    $auction = app(AuctionService::class)->create($listing->sellerProfile->user, [
        'listing_id' => $listing->id,
        'type' => AuctionType::Normal->value,
        'quantity' => 2,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ]);

    expect($auction->status)->toBe(AuctionStatus::Draft);

    app(MarketplaceModerationService::class)->reviewListing(
        User::factory()->create(),
        $listing,
        'approved',
        'Product and auction details meet marketplace requirements.',
    );

    expect($auction->refresh()->status)->toBe(AuctionStatus::Scheduled)
        ->and($listing->refresh()->reserved_quantity)->toBe(2);
});

test('sellers cannot edit or remove an auction after it is scheduled', function () {
    enableAuctions();
    $listing = Listing::factory()->create();
    $auction = app(AuctionService::class)->create($listing->sellerProfile->user, [
        'listing_id' => $listing->id,
        'type' => AuctionType::Normal->value,
        'quantity' => 1,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ]);
    $attributes = [
        'type' => AuctionType::Normal->value,
        'quantity' => 1,
        'starting_price' => '1200',
        'minimum_increment' => '100',
        'starts_at' => now()->addHours(2)->toDateTimeString(),
        'ends_at' => now()->addDays(2)->toDateTimeString(),
    ];

    expect(fn () => app(AuctionService::class)->updateDraft($listing->sellerProfile->user, $auction->id, $attributes))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(AuctionService::class)->deleteDraft($listing->sellerProfile->user, $auction->id))
        ->toThrow(AuthorizationException::class);
});

test('a seller can remove an unscheduled auction draft', function () {
    enableAuctions();
    $listing = Listing::factory()->create(['status' => 'draft', 'approved_at' => null]);
    $auction = app(AuctionService::class)->create($listing->sellerProfile->user, [
        'listing_id' => $listing->id,
        'type' => AuctionType::Normal->value,
        'quantity' => 1,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ]);

    $this->actingAs($listing->sellerProfile->user)
        ->delete(route('seller.auctions.destroy', $auction))
        ->assertRedirect(route('seller.auctions.index'));

    $this->assertSoftDeleted('auctions', ['id' => $auction->id]);
});

test('a seller can save an auction-only product and auction as one draft', function () {
    enableAuctions();
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)->post(route('seller.listings.store'), [
        'title' => 'Auction only camera',
        'product_type' => 'simple',
        'stock_quantity' => 4,
        'is_retail_enabled' => false,
        'is_wholesale_enabled' => false,
        'auction_enabled' => true,
        'auction' => [
            'type' => 'blind',
            'quantity' => 2,
            'starting_price' => '5000',
            'minimum_increment' => '250',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(3)->toDateTimeString(),
        ],
        'submit_for_review' => false,
    ])->assertRedirect(route('seller.listings.index', absolute: false));

    $listing = Listing::query()->sole();
    expect($listing->is_retail_enabled)->toBeFalse()
        ->and($listing->is_wholesale_enabled)->toBeFalse()
        ->and($listing->auction->status)->toBe(AuctionStatus::Draft)
        ->and($listing->auction->type)->toBe(AuctionType::Blind)
        ->and($listing->auction->quantity)->toBe(2);
});

test('scheduled auctions activate even if the master flag is later disabled', function () {
    enableAuctions();
    $auction = Auction::factory()->create([
        'status' => AuctionStatus::Scheduled,
        'starts_at' => now()->subMinute(),
        'inventory_reserved_at' => now()->subHour(),
    ]);
    MarketplaceSetting::query()->where('key', 'auction.enabled')->update(['value' => false]);

    app(AuctionLifecycleService::class)->activate($auction->id);

    expect($auction->refresh()->status)->toBe(AuctionStatus::Live);
});

test('closing without bids releases the reserved inventory', function () {
    enableAuctions();
    $listing = Listing::factory()->create(['stock_quantity' => 5, 'reserved_quantity' => 2]);
    $auction = Auction::factory()->create([
        'listing_id' => $listing->id,
        'quantity' => 2,
        'ends_at' => now()->subSecond(),
        'inventory_reserved_at' => now()->subHour(),
    ]);

    app(AuctionLifecycleService::class)->close($auction->id);

    expect($auction->refresh()->status)->toBe(AuctionStatus::EndedNoBids)
        ->and($listing->refresh()->reserved_quantity)->toBe(0);
});

test('admins can audit feature flags and cancel unpaid auctions with a reason', function () {
    enableAuctions();
    $listing = Listing::factory()->create(['stock_quantity' => 5]);
    $auction = app(AuctionService::class)->create($listing->sellerProfile->user, [
        'listing_id' => $listing->id,
        'type' => 'normal',
        'quantity' => 2,
        'starting_price' => '1000',
        'minimum_increment' => '100',
        'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addDays(2)->toDateTimeString(),
    ]);
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => 'admin', 'label' => 'Admin']));

    app(AuctionService::class)->updateFlags($admin, ['auction.enabled' => false]);
    app(AuctionService::class)->cancel($admin, $auction->id, 'Seller product requires an administrative cancellation.');

    expect($auction->refresh()->status)->toBe(AuctionStatus::Cancelled)
        ->and($listing->refresh()->reserved_quantity)->toBe(0)
        ->and(AuditLog::query()->where('action', 'auction.setting_updated')->where('actor_id', $admin->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'auction.cancelled')->where('reason', 'Seller product requires an administrative cancellation.')->exists())->toBeTrue();
});

test('normal auctions accept direct incremented per-unit bids', function () {
    enableAuctions();
    $auction = Auction::factory()->create(['current_price' => null]);
    $firstBuyer = User::factory()->create();
    $secondBuyer = User::factory()->create();

    $first = app(PlaceBidService::class)->place($firstBuyer, $auction->id, '10000');

    expect($first->amount)->toBe('10000.00')
        ->and($first->maximum_amount)->toBeNull()
        ->and($auction->refresh()->current_price)->toBe('10000.00');

    expect(fn () => app(PlaceBidService::class)->place($secondBuyer, $auction->id, '10499'))
        ->toThrow(InvalidAuctionBidException::class);

    app(PlaceBidService::class)->place($secondBuyer, $auction->id, '12000');
    expect($auction->refresh()->current_price)->toBe('12000.00');
});

test('blind auctions hide live market bids but expose the viewers own highest bid', function () {
    enableAuctions();
    $auction = Auction::factory()->create([
        'type' => AuctionType::Blind,
        'current_price' => null,
    ]);
    $buyer = User::factory()->create();
    app(PlaceBidService::class)->place($buyer, $auction->id, '13000');
    app(PlaceBidService::class)->place(User::factory()->create(), $auction->id, '15000');

    $this->actingAs($buyer)
        ->get(route('listings.show', $auction->listing->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.auction.currentPrice', null)
            ->where('listing.auction.viewerBid', '13000.00')
            ->where('listing.auction.bidCount', 2));
});

test('time extended auctions move the deadline to a full rolling window', function () {
    enableAuctions();
    $auction = Auction::factory()->create([
        'type' => AuctionType::TimeExtended,
        'current_price' => null,
        'extension_window_minutes' => 7,
        'ends_at' => now()->addMinutes(3),
    ]);

    app(PlaceBidService::class)->place(User::factory()->create(), $auction->id, '10000');

    expect($auction->refresh()->ends_at->diffInSeconds(now()->addMinutes(7)))->toBeLessThan(2);
});

test('closing ranks each bidder once and gives the first bidder a 24 hour offer', function () {
    enableAuctions();
    Notification::fake();
    $auction = Auction::factory()->create(['type' => AuctionType::Blind, 'current_price' => null, 'ends_at' => now()->addMinute()]);
    $firstBuyer = User::factory()->create();
    $secondBuyer = User::factory()->create();
    app(PlaceBidService::class)->place($firstBuyer, $auction->id, '12000');
    app(PlaceBidService::class)->place($secondBuyer, $auction->id, '12000');
    app(PlaceBidService::class)->place($firstBuyer, $auction->id, '13000');
    app(PlaceBidService::class)->place($secondBuyer, $auction->id, '13000');
    $auction->update(['ends_at' => now()->subSecond()]);

    app(AuctionLifecycleService::class)->close($auction->id);

    $offers = $auction->offers()->orderBy('rank')->get();
    expect($auction->refresh()->status)->toBe(AuctionStatus::OfferPending)
        ->and($offers)->toHaveCount(2)
        ->and($offers[0]->buyer_id)->toBe($firstBuyer->id)
        ->and($offers[0]->unit_price)->toBe('13000.00')
        ->and($offers[0]->status)->toBe(AuctionOfferStatus::Offered)
        ->and(abs($offers[0]->expires_at->diffInHours($offers[0]->offered_at)))->toBe(24.0)
        ->and($offers[1]->status)->toBe(AuctionOfferStatus::Waiting);
    Notification::assertSentTo($firstBuyer, AuctionOfferNotification::class);
});

test('an expired offer rolls to the next bidder with a fresh window', function () {
    enableAuctions();
    Notification::fake();
    $auction = Auction::factory()->create(['current_price' => null, 'ends_at' => now()->subSecond()]);
    $firstBuyer = User::factory()->create();
    $secondBuyer = User::factory()->create();
    $auction->bids()->create(['buyer_id' => $firstBuyer->id, 'amount' => 15000, 'is_proxy' => false]);
    $auction->bids()->create(['buyer_id' => $secondBuyer->id, 'amount' => 14000, 'is_proxy' => false]);
    app(AuctionLifecycleService::class)->close($auction->id);
    $firstOffer = $auction->offers()->where('rank', 1)->firstOrFail();

    Carbon::setTestNow($firstOffer->expires_at->addSecond());
    app(AuctionLifecycleService::class)->expireOffer($firstOffer->id);

    $secondOffer = $auction->offers()->where('rank', 2)->firstOrFail();
    expect($firstOffer->refresh()->status)->toBe(AuctionOfferStatus::Expired)
        ->and($secondOffer->status)->toBe(AuctionOfferStatus::Offered)
        ->and(abs($secondOffer->expires_at->diffInHours(now())))->toBe(24.0);
    Notification::assertSentTo($secondBuyer, AuctionOfferNotification::class);
});

test('bidder exhaustion releases inventory and repeated lifecycle calls are idempotent', function () {
    enableAuctions();
    $listing = Listing::factory()->create(['stock_quantity' => 4, 'reserved_quantity' => 2]);
    $auction = Auction::factory()->create([
        'listing_id' => $listing->id,
        'quantity' => 2,
        'ends_at' => now()->subSecond(),
        'inventory_reserved_at' => now()->subHour(),
    ]);
    $buyer = User::factory()->create();
    $auction->bids()->create(['buyer_id' => $buyer->id, 'amount' => 15000, 'is_proxy' => false]);

    app(AuctionLifecycleService::class)->close($auction->id);
    app(AuctionLifecycleService::class)->close($auction->id);
    $offer = $auction->offers()->sole();
    Carbon::setTestNow($offer->expires_at->addSecond());

    app(AuctionLifecycleService::class)->expireOffer($offer->id);
    app(AuctionLifecycleService::class)->expireOffer($offer->id);

    expect($auction->refresh()->status)->toBe(AuctionStatus::BidderExhausted)
        ->and($auction->offers()->count())->toBe(1)
        ->and($listing->refresh()->reserved_quantity)->toBe(0)
        ->and($offer->refresh()->status)->toBe(AuctionOfferStatus::Expired);
});
