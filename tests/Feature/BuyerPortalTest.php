<?php

use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use App\PaymentAttemptStatus;
use Inertia\Testing\AssertableInertia as Assert;

function buyerOrderWithPackages(User $buyer, string $customerStatus, array $packageStatuses): CustomerOrder
{
    $order = CustomerOrder::factory()->create([
        'buyer_id' => $buyer->id,
        'status' => $customerStatus,
    ]);

    foreach ($packageStatuses as $status) {
        SellerOrder::factory()->create([
            'customer_order_id' => $order->id,
            'seller_profile_id' => SellerProfile::factory(),
            'status' => $status,
            'delivered_at' => $status === 'completed' ? now() : null,
        ]);
    }

    return $order;
}

test('buyer routes require authentication and email verification', function () {
    $this->get(route('buyer.dashboard'))->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get(route('buyer.orders.index'))
        ->assertRedirect(route('verification.notice'));
});

test('order stages classify mixed seller fulfilment deterministically', function () {
    $buyer = User::factory()->create();
    $toPay = buyerOrderWithPackages($buyer, 'pending_payment', ['pending_payment']);
    $processing = buyerOrderWithPackages($buyer, 'confirmed', ['paid', 'ready_to_ship']);
    $shipped = buyerOrderWithPackages($buyer, 'confirmed', ['ready_to_ship', 'completed']);
    $completed = buyerOrderWithPackages($buyer, 'confirmed', ['completed', 'completed']);
    $archived = buyerOrderWithPackages($buyer, 'expired', ['expired']);

    foreach ([
        'to_pay' => $toPay,
        'processing' => $processing,
        'shipped' => $shipped,
        'completed' => $completed,
        'archived' => $archived,
    ] as $stage => $expectedOrder) {
        $this->actingAs($buyer)->get(route('buyer.orders.index', ['stage' => $stage]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('buyer/orders/index')
                ->where('stage', $stage)
                ->has('orders.data', 1)
                ->where('orders.data.0.number', $expectedOrder->number)
                ->where("counts.{$stage}", 1));
    }
});

test('buyers can only open their own order details', function () {
    $buyer = User::factory()->create();
    $order = buyerOrderWithPackages($buyer, 'confirmed', ['paid']);

    $this->actingAs($buyer)->get(route('buyer.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/orders/show')
            ->where('order.number', $order->number)
            ->where('order.stage', 'processing'));

    $this->actingAs(User::factory()->create())->get(route('buyer.orders.show', $order))
        ->assertForbidden();
});

test('payment history exposes durable attempts with status filters and buyer isolation', function () {
    $buyer = User::factory()->create();
    $order = buyerOrderWithPackages($buyer, 'confirmed', ['paid']);
    $payment = Payment::factory()->create(['customer_order_id' => $order->id]);
    PaymentAttempt::factory()->for($payment)->create([
        'status' => PaymentAttemptStatus::Succeeded,
        'attempt_number' => 1,
    ]);
    PaymentAttempt::factory()->for($payment)->create([
        'status' => PaymentAttemptStatus::Failed,
        'attempt_number' => 2,
        'failure_summary' => 'Payment provider could not complete this attempt.',
    ]);

    $otherPayment = Payment::factory()->create();
    PaymentAttempt::factory()->for($otherPayment)->create();

    $this->actingAs($buyer)->get(route('buyer.payments.index', ['status' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/payments/index')
            ->where('status', 'failed')
            ->has('attempts.data', 1)
            ->where('attempts.data.0.order_number', $order->number)
            ->where('attempts.data.0.status', 'failed')
            ->where('attempts.data.0.failure_summary', 'Payment provider could not complete this attempt.'));
});

test('overview and buyer settings render within dedicated pages', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('buyer.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/overview')
            ->has('summary.order_counts')
            ->has('summary.security'));

    $this->actingAs($buyer)->get(route('buyer.settings.profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('buyer/settings/profile'));

    $this->actingAs($buyer)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('buyer.settings.security.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('buyer/settings/security'));
});

test('legacy payments are backfilled with a synthesized durable attempt', function () {
    $payment = Payment::factory()->create([
        'status' => 'paid',
        'checkout_session_id' => 'cs_legacy',
    ]);

    $migration = require database_path('migrations/2026_09_06_200200_backfill_payment_attempts.php');
    $migration->up();

    $attempt = $payment->attempts()->sole();

    expect($attempt->attempt_number)->toBe(1)
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($attempt->provider_session_id)->toBe('cs_legacy');
});
