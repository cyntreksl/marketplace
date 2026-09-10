<?php

use App\Models\AuctionOffer;
use App\Models\AuditLog;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\BuyerOrderCancelledNotification;
use App\Notifications\BuyerOrderStatusNotification;
use App\Notifications\CancellationRefundCompletedNotification;
use App\RefundStatus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function orderOperationsUser(string $role = Role::Admin): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::factory()->create(['name' => $role, 'label' => str($role)->headline()]));

    return $user;
}

/** @return array{buyer: User, customer_order: CustomerOrder, seller: SellerProfile, seller_order: SellerOrder, listing: Listing, variant: ListingVariant, payment: Payment} */
function cancellableOrderFixture(string $paymentMethod = 'stripe', array $sellerOrderAttributes = []): array
{
    $buyer = User::factory()->create(['name' => 'Buyer Example', 'email' => fake()->unique()->safeEmail()]);
    $seller = SellerProfile::factory()->create(['store_name' => 'Camera Centre']);
    $customerOrder = CustomerOrder::factory()->for($buyer, 'buyer')->create([
        'number' => 'PRO'.fake()->unique()->numerify('######'),
        'subtotal' => '1000.00',
        'shipping_total' => '250.00',
        'total' => '1250.00',
        'billing_address' => ['name' => 'Buyer Example', 'line_1' => '20 Main Street', 'city' => 'Colombo'],
    ]);
    $sellerOrder = SellerOrder::factory()->for($customerOrder)->for($seller)->create([
        'subtotal' => '1000.00',
        'shipping_charge' => '250.00',
        ...$sellerOrderAttributes,
    ]);
    $listing = Listing::factory()->for($seller)->create(['stock_quantity' => 10, 'reserved_quantity' => 3]);
    $variant = ListingVariant::factory()->for($listing)->create(['stock_quantity' => 10, 'reserved_quantity' => 3]);
    OrderItem::factory()->for($sellerOrder)->for($listing)->create([
        'listing_variant_id' => $variant->id,
        'title' => 'Professional camera',
        'quantity' => 2,
        'unit_price' => '500.00',
        'total' => '1000.00',
    ]);
    $payment = Payment::factory()->for($customerOrder)->create([
        'method' => $paymentMethod,
        'status' => $paymentMethod === 'cod' ? 'pending_collection' : 'paid',
        'provider_reference' => $paymentMethod === 'cod' ? null : 'pi_'.fake()->unique()->numerify('############'),
        'provider_payload' => ['client_secret' => 'must-never-leak'],
        'amount' => '1250.00',
    ]);

    return [
        'buyer' => $buyer,
        'customer_order' => $customerOrder,
        'seller' => $seller,
        'seller_order' => $sellerOrder,
        'listing' => $listing,
        'variant' => $variant,
        'payment' => $payment,
    ];
}

test('admin order index is paginated searchable filterable and includes buyer seller and payment summaries', function () {
    $admin = orderOperationsUser();
    foreach (range(1, 21) as $index) {
        $fixture = cancellableOrderFixture();
        $fixture['customer_order']->update([
            'status' => $index === 21 ? 'cancelled' : 'confirmed',
            'created_at' => now()->subDays($index),
        ]);
    }

    $this->actingAs($admin)->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/orders/index')
            ->has('orders.data', 20)
            ->where('orders.per_page', 20)
            ->where('orders.total', 21)
            ->where('orders.data.0.buyer.name', 'Buyer Example')
            ->where('orders.data.0.packages.0.store_name', 'Camera Centre')
            ->where('orders.data.0.payments.0.method', 'stripe'));

    $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'Professional camera', 'status' => 'cancelled', 'sort' => 'oldest']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('orders.total', 1)
            ->where('filters.status', 'cancelled')
            ->where('filters.sort', 'oldest'));

    $this->actingAs($admin)->get(route('admin.orders.index', ['sort' => 'invalid']))->assertSessionHasErrors('sort');
});

test('admin order detail includes nested operations data and excludes provider secrets', function () {
    $fixture = cancellableOrderFixture();

    $this->actingAs(orderOperationsUser(Role::SuperAdmin))->get(route('admin.orders.show', $fixture['customer_order']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/orders/show')
            ->where('order.number', $fixture['customer_order']->number)
            ->where('order.buyer.email', $fixture['buyer']->email)
            ->where('order.packages.0.number', $fixture['seller_order']->number)
            ->where('order.packages.0.items.0.title', 'Professional camera')
            ->where('order.packages.0.can_cancel', true)
            ->missing('order.payments.0.provider_reference')
            ->missing('order.payments.0.provider_payload')
            ->missing('order.payments.0.idempotency_key'));
});

test('only admin and super admin can access and operate on admin orders', function (string $actor) {
    $fixture = cancellableOrderFixture();
    $user = match ($actor) {
        'guest' => null,
        'admin' => orderOperationsUser(),
        'super_admin' => orderOperationsUser(Role::SuperAdmin),
        'finance_admin' => orderOperationsUser(Role::FinanceAdmin),
        'seller' => $fixture['seller']->user,
        default => User::factory()->create(),
    };
    $request = $user === null ? $this : $this->actingAs($user);
    $expected = in_array($actor, ['admin', 'super_admin'], true);

    $indexResponse = $request->get(route('admin.orders.index'));
    $actionResponse = $request->post(route('admin.orders.packages.processing', [$fixture['customer_order'], $fixture['seller_order']]));

    if ($expected) {
        $indexResponse->assertOk();
        $actionResponse->assertRedirect();
    } elseif ($actor === 'guest') {
        $indexResponse->assertRedirect();
        $actionResponse->assertRedirect();
    } else {
        $indexResponse->assertForbidden();
        $actionResponse->assertForbidden();
    }
})->with(['guest', 'admin', 'super_admin', 'finance_admin', 'seller', 'buyer']);

test('admin performs the complete seller workflow with scoped binding audit actors and notifications', function () {
    Notification::fake();
    $fixture = cancellableOrderFixture();
    $admin = orderOperationsUser();
    $routeArguments = [$fixture['customer_order'], $fixture['seller_order']];

    $this->actingAs($admin)->post(route('admin.orders.packages.processing', $routeArguments))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.orders.packages.processing', $routeArguments))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.orders.packages.ready', $routeArguments))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.orders.packages.shipped', $routeArguments), ['courier_name' => 'City Express'])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.orders.packages.delivered', $routeArguments))->assertRedirect();

    expect($fixture['seller_order']->refresh()->status)->toBe('completed')
        ->and($fixture['seller_order']->shipment->tracking_number)->toStartWith('MAN-')
        ->and(AuditLog::query()->where('auditable_id', $fixture['seller_order']->id)->where('actor_id', $admin->id)->count())->toBe(4)
        ->and(AuditLog::query()->where('action', 'seller_order.processing')->where('auditable_id', $fixture['seller_order']->id)->count())->toBe(1);
    Notification::assertSentToTimes($fixture['buyer'], BuyerOrderStatusNotification::class, 2);

    $otherOrder = cancellableOrderFixture()['customer_order'];
    $this->actingAs($admin)->post(route('admin.orders.packages.processing', [$otherOrder, $fixture['seller_order']]))->assertNotFound();
});

test('seller cancellation validates ownership reason state and releases inventory exactly once', function () {
    Notification::fake();
    $fixture = cancellableOrderFixture();
    $otherSeller = SellerProfile::factory()->create();

    $this->actingAs($otherSeller->user)->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'This belongs to another seller.'])->assertForbidden();
    $this->actingAs($fixture['seller']->user)->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'short'])->assertSessionHasErrors('reason');
    $this->actingAs($fixture['seller']->user)->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'This item is currently out of stock.'])->assertRedirect();
    $this->actingAs($fixture['seller']->user)->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'This repeated request should be harmless.'])->assertRedirect();

    expect($fixture['seller_order']->refresh()->status)->toBe('cancelled')
        ->and($fixture['seller_order']->cancellation_reason)->toBe('This item is currently out of stock.')
        ->and($fixture['seller_order']->cancelled_by)->toBe($fixture['seller']->user_id)
        ->and($fixture['listing']->refresh()->reserved_quantity)->toBe(1)
        ->and($fixture['variant']->refresh()->reserved_quantity)->toBe(1)
        ->and(Refund::query()->where('seller_order_id', $fixture['seller_order']->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'seller_order.cancelled')->count())->toBe(1);
    Notification::assertSentToTimes($fixture['buyer'], BuyerOrderCancelledNotification::class, 1);
});

test('only regular paid packages can be cancelled', function (array $attributes) {
    $fixture = cancellableOrderFixture(sellerOrderAttributes: $attributes);

    $this->actingAs($fixture['seller']->user)
        ->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'This package cannot be supplied now.'])
        ->assertSessionHasErrors('order');

    expect($fixture['seller_order']->refresh()->status)->not->toBe('cancelled')
        ->and($fixture['listing']->refresh()->reserved_quantity)->toBe(3);
})->with([
    'pending payment' => [['status' => 'pending_payment']],
    'processing' => [['status' => 'processing']],
    'shipped' => [['status' => 'shipped']],
    'completed' => [['status' => 'completed']],
]);

test('auction packages are excluded from seller cancellation', function () {
    $fixture = cancellableOrderFixture();
    $offer = AuctionOffer::factory()->create(['buyer_id' => $fixture['buyer']->id]);
    $fixture['customer_order']->update(['auction_offer_id' => $offer->id]);

    $this->actingAs($fixture['seller']->user)
        ->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'This auction package cannot be supplied.'])
        ->assertSessionHasErrors('order');

    expect($fixture['seller_order']->refresh()->status)->toBe('paid')
        ->and(Refund::query()->count())->toBe(0);
});

test('partial and complete cancellations preserve parent staging and cod requires no refund', function () {
    $fixture = cancellableOrderFixture('cod');
    $secondSeller = SellerProfile::factory()->create();
    $secondPackage = SellerOrder::factory()->for($fixture['customer_order'])->for($secondSeller)->create(['subtotal' => '500.00', 'shipping_charge' => '0.00']);
    OrderItem::factory()->for($secondPackage)->create();

    $this->actingAs($fixture['seller']->user)->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'The first package is out of stock.']);
    expect($fixture['customer_order']->refresh()->status)->toBe('confirmed')
        ->and($fixture['payment']->refresh()->status)->toBe('pending_collection')
        ->and(Refund::query()->count())->toBe(0);

    $this->actingAs($secondSeller->user)->post(route('seller.orders.cancel', $secondPackage), ['reason' => 'The second package is also unavailable.']);
    expect($fixture['customer_order']->refresh()->status)->toBe('cancelled')
        ->and($fixture['payment']->refresh()->status)->toBe('cancelled')
        ->and(Refund::query()->count())->toBe(0);

    $this->actingAs($fixture['buyer'])->get(route('buyer.orders.index', ['stage' => 'archived']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('orders.total', 1));
});

test('admin completes cancellation refunds within ceilings and recalculates payment status', function () {
    Notification::fake();
    $fixture = cancellableOrderFixture();
    $admin = orderOperationsUser();
    $arguments = [$fixture['customer_order'], $fixture['seller_order']];
    $this->actingAs($fixture['seller']->user)->post(route('seller.orders.cancel', $fixture['seller_order']), ['reason' => 'The product is no longer available.']);

    $refund = Refund::query()->where('seller_order_id', $fixture['seller_order']->id)->firstOrFail();
    expect($refund->amount)->toBeNull()->and($refund->status)->toBe(RefundStatus::Pending);

    $this->actingAs($admin)->post(route('admin.orders.packages.refund', $arguments), ['amount' => '1250.01', 'reference' => 'MANUAL-OVER'])->assertSessionHasErrors('amount');
    $this->actingAs($admin)->post(route('admin.orders.packages.refund', $arguments), ['amount' => '', 'reference' => ''])->assertSessionHasErrors(['amount', 'reference']);
    $this->actingAs($admin)->post(route('admin.orders.packages.refund', $arguments), ['amount' => '1000.00', 'reference' => 'BANK-10001'])->assertRedirect();

    expect($refund->refresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->amount)->toBe('1000.00')
        ->and($refund->manual_reference)->toBe('BANK-10001')
        ->and($refund->processed_by)->toBe($admin->id)
        ->and($fixture['payment']->refresh()->status)->toBe('partially_refunded')
        ->and(AuditLog::query()->where('action', 'refund.cancellation_completed_manually')->where('actor_id', $admin->id)->exists())->toBeTrue();
    Notification::assertSentTo($fixture['buyer'], CancellationRefundCompletedNotification::class);
});

test('cancellation refund cannot exceed the payment balance after other successful refunds', function () {
    $fixture = cancellableOrderFixture();
    $siblingPackage = SellerOrder::factory()->for($fixture['customer_order'])->create(['status' => 'cancelled']);
    Refund::query()->create([
        'seller_order_id' => $siblingPackage->id,
        'payment_id' => $fixture['payment']->id,
        'method' => 'stripe',
        'amount' => '500.00',
        'status' => RefundStatus::Succeeded,
        'idempotency_key' => (string) str()->uuid(),
        'completed_at' => now(),
    ]);
    $this->actingAs($fixture['seller']->user)->post(
        route('seller.orders.cancel', $fixture['seller_order']),
        ['reason' => 'The product is no longer available.'],
    );

    $this->actingAs(orderOperationsUser())->post(
        route('admin.orders.packages.refund', [$fixture['customer_order'], $fixture['seller_order']]),
        ['amount' => '750.01', 'reference' => 'BANK-OVER-BALANCE'],
    )->assertSessionHasErrors('amount');

    expect($fixture['seller_order']->refresh()->refund->status)->toBe(RefundStatus::Pending);
});

test('full cancellation refund is visible in seller buyer and tracking details', function () {
    $fixture = cancellableOrderFixture();
    $admin = orderOperationsUser();
    $this->actingAs($admin)->post(route('admin.orders.packages.cancel', [$fixture['customer_order'], $fixture['seller_order']]), ['reason' => 'Inventory reconciliation found no available stock.']);
    $this->actingAs($admin)->post(route('admin.orders.packages.refund', [$fixture['customer_order'], $fixture['seller_order']]), ['amount' => '1250.00', 'reference' => 'BANK-FULL-1']);

    expect($fixture['payment']->refresh()->status)->toBe('refunded');

    $this->actingAs($fixture['seller']->user)->get(route('seller.orders.show', $fixture['seller_order']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('order.cancellation.reason', 'Inventory reconciliation found no available stock.')
            ->where('order.refund.status', 'succeeded')
            ->where('order.refund.amount', '1250.00'));
    $this->actingAs($fixture['buyer'])->get(route('buyer.orders.show', $fixture['customer_order']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('order.stage', 'archived')
            ->where('order.seller_orders.0.cancellation.reason', 'Inventory reconciliation found no available stock.')
            ->where('order.seller_orders.0.refund.status', 'succeeded'));
    $this->postJson(route('order-tracking.store'), ['number' => $fixture['customer_order']->number, 'email' => $fixture['buyer']->email])
        ->assertOk()
        ->assertJsonPath('order.shipments.0.status', 'cancelled')
        ->assertJsonPath('order.shipments.0.cancellationReason', 'Inventory reconciliation found no available stock.');
});
