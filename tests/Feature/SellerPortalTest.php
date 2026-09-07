<?php

use App\Models\AuditLog;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\ProductQuestion;
use App\Models\ReturnRequest;
use App\Models\SellerLedgerEntry;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Notifications\BuyerOrderStatusNotification;
use App\Services\SellerPortalService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function sellerOrderForPortal(SellerProfile $profile, array $attributes = []): SellerOrder
{
    $customerOrder = CustomerOrder::factory()->create([
        'shipping_address' => [
            'name' => 'Nimali Perera',
            'address_line_one' => '14 Galle Road',
            'city' => 'Colombo',
            'phone' => '0771234567',
        ],
    ]);
    $order = SellerOrder::factory()->for($profile)->for($customerOrder)->create($attributes);
    OrderItem::factory()->for($order)->create(['title' => 'Professional camera']);

    return $order;
}

test('seller dashboard shows scoped business metrics', function () {
    $profile = SellerProfile::factory()->create();
    sellerOrderForPortal($profile, ['status' => 'paid']);
    sellerOrderForPortal($profile, ['status' => 'ready_to_ship']);
    sellerOrderForPortal(SellerProfile::factory()->create(), ['status' => 'paid']);

    $this->actingAs($profile->user)->get(route('seller.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('seller/overview')
            ->where('metrics.orders_needing_action', 1)
            ->where('metrics.ready_to_dispatch', 1));
});

test('seller dashboard activity serializes recent orders without lazy loading', function () {
    $profile = SellerProfile::factory()->create();
    $order = sellerOrderForPortal($profile, ['status' => 'paid']);

    $activity = app(SellerPortalService::class)->activity($profile->user);

    expect($activity['recent_orders'])->toHaveCount(1)
        ->and($activity['recent_orders'][0]['number'])->toBe($order->number)
        ->and($activity['recent_orders'][0]['shipment'])->toBeNull();
});

test('seller orders are owner scoped searchable filterable and paginated', function () {
    $profile = SellerProfile::factory()->create();
    foreach (range(1, 16) as $index) {
        sellerOrderForPortal($profile, ['number' => 'SELL-PORTAL-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'status' => $index === 16 ? 'processing' : 'paid']);
    }
    sellerOrderForPortal(SellerProfile::factory()->create(), ['number' => 'SELL-OTHER-01']);

    $this->actingAs($profile->user)->get(route('seller.orders.index', ['status' => 'paid', 'sort' => 'oldest', 'page' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('seller/orders/index')
            ->has('orders.data', 15)
            ->where('orders.total', 15)
            ->where('orders.per_page', 15)
            ->where('filters.status', 'paid')
            ->where('filters.sort', 'oldest'));

    $this->actingAs($profile->user)->get(route('seller.orders.index', ['q' => 'Professional camera']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('orders.total', 16));

    $this->actingAs($profile->user)->get(route('seller.orders.index', ['sort' => 'oldest']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('orders.last_page', 2)
            ->where('orders.current_page', 1)
            ->where('filters.sort', 'oldest'));
});

test('seller cannot view another sellers order detail', function () {
    $owner = SellerProfile::factory()->create();
    $intruder = SellerProfile::factory()->create();
    $order = sellerOrderForPortal($owner);

    $this->actingAs($intruder->user)->get(route('seller.orders.show', $order))->assertForbidden();
});

test('seller order workflow enforces ordered idempotent transitions and notifies buyer', function () {
    Notification::fake();
    $profile = SellerProfile::factory()->create();
    $order = sellerOrderForPortal($profile, ['status' => 'paid']);
    $buyer = $order->customerOrder->buyer;

    $this->actingAs($profile->user)->post(route('seller.orders.ready', $order))->assertSessionHasErrors('status');
    $this->actingAs($profile->user)->post(route('seller.orders.processing', $order))->assertRedirect();
    $this->actingAs($profile->user)->post(route('seller.orders.processing', $order))->assertRedirect();
    expect(AuditLog::query()->where('action', 'seller_order.processing')->where('auditable_id', $order->id)->count())->toBe(1);

    $this->actingAs($profile->user)->post(route('seller.orders.ready', $order))->assertRedirect();
    $this->actingAs($profile->user)->post(route('seller.orders.shipped', $order), ['courier_name' => 'City Express'])->assertRedirect();
    $this->actingAs($profile->user)->post(route('seller.orders.shipped', $order), ['courier_name' => 'City Express'])->assertRedirect();

    expect($order->refresh()->status)->toBe('shipped')
        ->and($order->shipment->tracking_number)->toStartWith('MAN-')
        ->and(AuditLog::query()->where('action', 'seller_order.shipped')->where('auditable_id', $order->id)->count())->toBe(1);
    Notification::assertSentToTimes($buyer, BuyerOrderStatusNotification::class, 1);

    $this->actingAs($profile->user)->post(route('seller.orders.delivered', $order))->assertRedirect();
    expect($order->refresh()->status)->toBe('completed')
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->completed_at)->not->toBeNull();
    Notification::assertSentToTimes($buyer, BuyerOrderStatusNotification::class, 2);
});

test('seller order detail exposes the real timeline without sensitive payment data', function () {
    $profile = SellerProfile::factory()->create();
    $order = sellerOrderForPortal($profile, [
        'status' => 'processing',
        'processing_at' => now(),
    ]);

    $this->actingAs($profile->user)->get(route('seller.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('seller/orders/show')
            ->where('order.number', $order->number)
            ->where('order.recipient.name', 'Nimali Perera')
            ->where('order.timeline.1.status', 'processing')
            ->where('order.timeline.2.at', null)
            ->missing('order.customer_order.payments.0.provider_payload'));
});

test('products use twenty row server side pagination', function () {
    $profile = SellerProfile::factory()->create();
    Listing::factory()->count(21)->for($profile)->create();

    $this->actingAs($profile->user)->get(route('seller.listings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('seller/listings/index')
            ->has('listings.data', 20)
            ->where('listings.total', 21)
            ->where('listings.per_page', 20));
});

test('returns and questions use twenty row scoped pagination', function () {
    $profile = SellerProfile::factory()->create();
    $order = sellerOrderForPortal($profile, ['status' => 'completed', 'delivered_at' => now()]);
    $item = $order->items->firstOrFail();
    ReturnRequest::factory()->count(21)->for($item, 'orderItem')->create();
    $listing = Listing::factory()->for($profile)->create();
    ProductQuestion::factory()->count(21)->for($listing)->create();

    $this->actingAs($profile->user)->get(route('seller.returns.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('returns.current_page', 2)
            ->has('returns.data', 1)
            ->where('returns.per_page', 20));

    $this->actingAs($profile->user)->get(route('product-questions.index', ['status' => 'unanswered', 'page' => 2]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('questions.current_page', 2)
            ->has('questions.data', 1)
            ->where('questions.per_page', 20)
            ->where('filters.status', 'unanswered'));
});

test('wallet datasets paginate independently', function () {
    $profile = SellerProfile::factory()->create();
    SellerLedgerEntry::factory()->count(21)->for($profile)->create();
    PayoutRequest::factory()->count(11)->for($profile)->create();

    $this->actingAs($profile->user)->get(route('seller.wallet.index', ['transactions_page' => 2, 'payouts_page' => 2]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('entries.current_page', 2)
            ->has('entries.data', 1)
            ->where('entries.per_page', 20)
            ->where('payouts.current_page', 2)
            ->has('payouts.data', 1)
            ->where('payouts.per_page', 10));
});
