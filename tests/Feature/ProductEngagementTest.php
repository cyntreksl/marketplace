<?php

use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\User;
use App\Models\Watchlist;
use App\SellerOrderStatus;
use Inertia\Testing\AssertableInertia as Assert;

function productEngagementAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create([
        'name' => Role::Admin,
        'label' => 'Administrator',
    ]));

    return $admin;
}

test('public engagement combines baselines with eligible orders and active watchlists', function () {
    $listing = Listing::factory()->create([
        'sold_count_baseline' => 4,
        'watch_count_baseline' => 1,
        'view_count_baseline' => 3,
        'view_count' => 7,
    ]);

    foreach ([
        [SellerOrderStatus::Paid, 1],
        [SellerOrderStatus::Processing, 2],
        [SellerOrderStatus::ReadyToShip, 3],
        [SellerOrderStatus::Shipped, 4],
        [SellerOrderStatus::Completed, 5],
        [SellerOrderStatus::PendingPayment, 10],
        [SellerOrderStatus::Expired, 20],
        [SellerOrderStatus::Cancelled, 30],
    ] as [$status, $quantity]) {
        $sellerOrder = SellerOrder::factory()->create(['status' => $status->value]);
        OrderItem::factory()->for($sellerOrder)->for($listing)->create(['quantity' => $quantity]);
    }

    Watchlist::factory()->count(2)->for($listing)->create();
    $removedWatch = Watchlist::factory()->for($listing)->create();
    $removedWatch->delete();

    $this->withHeader('User-Agent', 'Mozilla/5.0 EngagementTest')
        ->get(route('listings.show', $listing->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('engagement.soldCount', 19)
            ->where('engagement.watcherCount', 3)
            ->where('engagement.viewCount', 11)
            ->missing('engagement.sold.baseline')
            ->missing('listing.sold_count_baseline')
            ->missing('listing.watch_count_baseline')
            ->missing('listing.view_count_baseline'));
});

test('an admin can update audited engagement baselines and see actual totals', function () {
    $admin = productEngagementAdmin();
    $listing = Listing::factory()->create(['view_count' => 8]);
    Watchlist::factory()->count(2)->for($listing)->create();
    $sellerOrder = SellerOrder::factory()->create(['status' => SellerOrderStatus::Paid->value]);
    OrderItem::factory()->for($sellerOrder)->for($listing)->create(['quantity' => 3]);

    $this->actingAs($admin)->patch(route('admin.listings.merchandising.update', $listing), [
        'sold_count_baseline' => 7,
        'watch_count_baseline' => 5,
        'view_count_baseline' => 12,
        'reason' => 'Document verified off-platform campaign history',
    ])->assertRedirect();

    expect($listing->refresh())
        ->sold_count_baseline->toBe(7)
        ->watch_count_baseline->toBe(5)
        ->view_count_baseline->toBe(12);

    $this->actingAs($admin)->get(route('admin.listings.show', $listing))
        ->assertInertia(fn (Assert $page) => $page
            ->where('engagement.sold', ['baseline' => 7, 'actual' => 3, 'total' => 10])
            ->where('engagement.watchers', ['baseline' => 5, 'actual' => 2, 'total' => 7])
            ->where('engagement.views', ['baseline' => 12, 'actual' => 8, 'total' => 20]));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'listing.merchandising_updated',
        'reason' => 'Document verified off-platform campaign history',
    ]);
});

test('engagement baselines are bounded and restricted to authorized admins', function () {
    $listing = Listing::factory()->create();

    $this->actingAs(productEngagementAdmin())->patch(route('admin.listings.merchandising.update', $listing), [
        'sold_count_baseline' => -1,
        'watch_count_baseline' => 10000001,
        'view_count_baseline' => 'not-a-number',
        'reason' => 'Reject invalid historical engagement figures',
    ])->assertSessionHasErrors(['sold_count_baseline', 'watch_count_baseline', 'view_count_baseline']);

    $this->actingAs(User::factory()->create())->patch(route('admin.listings.merchandising.update', $listing), [
        'sold_count_baseline' => 1,
        'watch_count_baseline' => 2,
        'view_count_baseline' => 10,
        'reason' => 'Unauthorized engagement edit attempt',
    ])->assertForbidden();
});

test('a human listing view is counted once per listing per session', function () {
    $firstListing = Listing::factory()->create();
    $secondListing = Listing::factory()->create();
    $firstListingUpdatedAt = $firstListing->updated_at->copy();

    $browser = $this->withHeader('User-Agent', 'Mozilla/5.0 ViewCounterTest');
    $browser->get(route('listings.show', $firstListing->slug))->assertOk();
    $browser->get(route('listings.show', $firstListing->slug))->assertOk();
    $browser->get(route('listings.show', $secondListing->slug))->assertOk();

    $firstListing->refresh();

    expect($firstListing->view_count)->toBe(1)
        ->and($firstListing->updated_at->equalTo($firstListingUpdatedAt))->toBeTrue()
        ->and($secondListing->refresh()->view_count)->toBe(1);
});

test('bots prefetches and prerenders do not increment listing views', function (array $headers) {
    $listing = Listing::factory()->create();

    $request = $this;
    foreach ($headers as $name => $value) {
        $request = $request->withHeader($name, $value);
    }

    $request->get(route('listings.show', $listing->slug))->assertOk();

    expect($listing->refresh()->view_count)->toBe(0);
})->with([
    'crawler user agent' => [['User-Agent' => 'Googlebot/2.1']],
    'prefetch purpose' => [['User-Agent' => 'Mozilla/5.0', 'Purpose' => 'prefetch']],
    'secure prefetch purpose' => [['User-Agent' => 'Mozilla/5.0', 'Sec-Purpose' => 'prefetch']],
    'prerender purpose' => [['User-Agent' => 'Mozilla/5.0', 'X-Purpose' => 'prerender']],
]);
