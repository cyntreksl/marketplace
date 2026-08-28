<?php

use App\Models\CustomerOrder;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\NewReturnRequestNotification;
use App\Notifications\RefundOutcomeNotification;
use App\Notifications\RefundReadyNotification;
use App\Notifications\ReturnDecisionNotification;
use App\RefundStatus;
use App\ReturnReason;
use App\ReturnStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function createOperationsUser(string $role = Role::Admin): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::factory()->create(['name' => $role, 'label' => 'Operations']));

    return $user;
}

/** @return array{buyer: User, seller: User, item: OrderItem, payment: Payment, return_request: ReturnRequest} */
function createRefundableReturn(string $method = 'stripe', ReturnStatus $status = ReturnStatus::Approved): array
{
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $sellerProfile = SellerProfile::factory()->for($seller)->create();
    $customerOrder = CustomerOrder::factory()->for($buyer, 'buyer')->create();
    $sellerOrder = SellerOrder::factory()->for($customerOrder)->for($sellerProfile)->delivered()->create();
    $item = OrderItem::factory()->for($sellerOrder)->create(['quantity' => 3, 'unit_price' => '500.00', 'total' => '1500.00']);
    $payment = Payment::factory()->for($customerOrder)->create([
        'method' => $method,
        'status' => $method === 'cod' ? 'pending_collection' : 'paid',
        'amount' => '1500.00',
        'provider_reference' => $method === 'stripe' ? 'pi_partial_example' : null,
    ]);
    $returnRequest = ReturnRequest::factory()->for($item, 'orderItem')->for($buyer, 'buyer')->create([
        'quantity' => 2,
        'refund_amount' => '1000.00',
        'status' => $status,
        'resolution_reason' => 'The seller approved this return request.',
        'seller_responded_at' => now(),
    ]);

    return ['buyer' => $buyer, 'seller' => $seller, 'item' => $item, 'payment' => $payment, 'return_request' => $returnRequest];
}

test('only operations users can access the refund queue', function () {
    ['return_request' => $returnRequest] = createRefundableReturn();
    $admin = createOperationsUser();

    $this->actingAs($admin)
        ->get(route('admin.returns.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/returns/index')
            ->has('returns.data', 1)
            ->where('returns.data.0.id', $returnRequest->id));

    $this->actingAs(User::factory()->create())->get(route('admin.returns.index'))->assertForbidden();
});

test('an approved return can be marked ready with one auditable refund record', function () {
    ['payment' => $payment, 'return_request' => $returnRequest] = createRefundableReturn();
    $admin = createOperationsUser();

    $this->actingAs($admin)
        ->post(route('admin.returns.ready', $returnRequest))
        ->assertRedirect(route('admin.returns.index'));

    $returnRequest->refresh();
    $refund = Refund::query()->firstOrFail();

    expect($returnRequest->status)->toBe(ReturnStatus::RefundPending)
        ->and($returnRequest->refund_ready_at)->not->toBeNull()
        ->and($refund->payment_id)->toBe($payment->id)
        ->and($refund->amount)->toBe('1000.00')
        ->and($refund->status)->toBe(RefundStatus::Pending)
        ->and($refund->idempotency_key)->not->toBeEmpty();

    $this->actingAs($admin)
        ->post(route('admin.returns.ready', $returnRequest))
        ->assertSessionHasErrors('refund');

    expect(Refund::query()->count())->toBe(1);
});

test('stripe partial refunds send the idempotency key and persist provider success', function () {
    ['payment' => $payment, 'return_request' => $returnRequest] = createRefundableReturn();
    $admin = createOperationsUser();
    $this->actingAs($admin)->post(route('admin.returns.ready', $returnRequest));
    $refund = Refund::query()->firstOrFail();

    Http::fake([
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_partial_123', 'status' => 'succeeded']),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.returns.refund', $returnRequest))
        ->assertRedirect(route('admin.returns.index'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.stripe.com/v1/refunds'
        && $request->hasHeader('Idempotency-Key', $refund->idempotency_key)
        && $request['payment_intent'] === $payment->provider_reference
        && $request['amount'] === 100000);

    expect($refund->refresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_reference)->toBe('re_partial_123')
        ->and($refund->completed_at)->not->toBeNull()
        ->and($returnRequest->refresh()->status)->toBe(ReturnStatus::Refunded);
});

test('failed card refunds remain visible and retry with the same idempotency key', function () {
    Notification::fake();
    ['buyer' => $buyer, 'return_request' => $returnRequest] = createRefundableReturn();
    $admin = createOperationsUser();
    $this->actingAs($admin)->post(route('admin.returns.ready', $returnRequest));
    $refund = Refund::query()->firstOrFail();

    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        return $attempts <= 2
            ? Http::response(['error' => ['message' => 'Provider unavailable']], 500)
            : Http::response(['id' => 're_retry_456', 'status' => 'succeeded']);
    });
    $this->actingAs($admin)->post(route('admin.returns.refund', $returnRequest))->assertRedirect();

    expect($refund->refresh()->status)->toBe(RefundStatus::Failed)
        ->and($refund->failure_details)->not->toBeNull()
        ->and($returnRequest->refresh()->status)->toBe(ReturnStatus::RefundFailed);
    Notification::assertSentTo($buyer, RefundOutcomeNotification::class, fn ($notification): bool => $notification->status === 'failed');

    $originalKey = $refund->idempotency_key;
    $this->actingAs($admin)->post(route('admin.returns.refund', $returnRequest))->assertRedirect();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', $originalKey));
    $refund->refresh();
    expect($refund->failure_details)->toBeNull()
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->idempotency_key)->toBe($originalKey)
        ->and($returnRequest->refresh()->status)->toBe(ReturnStatus::Refunded);
});

test('pending stripe results stay in the operations queue', function () {
    ['return_request' => $returnRequest] = createRefundableReturn();
    $admin = createOperationsUser();
    $this->actingAs($admin)->post(route('admin.returns.ready', $returnRequest));
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_pending_1', 'status' => 'pending'])]);

    $this->actingAs($admin)->post(route('admin.returns.refund', $returnRequest))->assertRedirect();

    expect(Refund::query()->firstOrFail()->status)->toBe(RefundStatus::Pending)
        ->and($returnRequest->refresh()->status)->toBe(ReturnStatus::RefundPending);
});

test('bank and cod refunds require and preserve a manual reference', function (string $method) {
    ['return_request' => $returnRequest] = createRefundableReturn($method);
    $admin = createOperationsUser(Role::FinanceAdmin);
    $this->actingAs($admin)->post(route('admin.returns.ready', $returnRequest));

    $this->actingAs($admin)
        ->post(route('admin.returns.manual', $returnRequest), ['reference' => ''])
        ->assertSessionHasErrors('reference');

    $this->actingAs($admin)
        ->post(route('admin.returns.manual', $returnRequest), ['reference' => 'MANUAL-REF-7788'])
        ->assertRedirect(route('admin.returns.index'));

    $refund = Refund::query()->firstOrFail();
    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->manual_reference)->toBe('MANUAL-REF-7788')
        ->and($refund->completed_at)->not->toBeNull()
        ->and($returnRequest->refresh()->status)->toBe(ReturnStatus::Refunded);
})->with(['bank_transfer', 'cod']);

test('rejected and requested returns cannot enter the refund workflow', function (ReturnStatus $status) {
    ['return_request' => $returnRequest] = createRefundableReturn(status: $status);
    $admin = createOperationsUser();

    $this->actingAs($admin)
        ->post(route('admin.returns.ready', $returnRequest))
        ->assertSessionHasErrors('refund');

    expect(Refund::query()->count())->toBe(0);
})->with([ReturnStatus::Requested, ReturnStatus::Rejected]);

test('return and refund notifications are queued after commit for each workflow event', function () {
    Notification::fake();
    $admin = createOperationsUser();
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $sellerProfile = SellerProfile::factory()->for($seller)->create();
    $customerOrder = CustomerOrder::factory()->for($buyer, 'buyer')->create();
    $sellerOrder = SellerOrder::factory()->for($customerOrder)->for($sellerProfile)->delivered()->create();
    $item = OrderItem::factory()->for($sellerOrder)->create();
    Payment::factory()->for($customerOrder)->create(['method' => 'cod', 'status' => 'pending_collection', 'provider_reference' => null]);

    $this->actingAs($buyer)->post(route('buyer.returns.store'), [
        'order_item_id' => $item->id,
        'quantity' => 1,
        'reason' => ReturnReason::Damaged->value,
        'description' => 'The item arrived with a damaged enclosure.',
    ]);
    $returnRequest = ReturnRequest::query()->firstOrFail();

    Notification::assertSentTo($seller, NewReturnRequestNotification::class, fn ($notification): bool => $notification->afterCommit === true);

    $this->actingAs($seller)->patch(route('seller.returns.update', $returnRequest), [
        'decision' => ReturnStatus::Approved->value,
        'response_reason' => 'The photographs show delivery damage clearly.',
    ]);

    Notification::assertSentTo($buyer, ReturnDecisionNotification::class, fn ($notification): bool => $notification->afterCommit === true);
    Notification::assertSentTo($admin, RefundReadyNotification::class, fn ($notification): bool => $notification->afterCommit === true);

    $this->actingAs($admin)->post(route('admin.returns.ready', $returnRequest));
    $this->actingAs($admin)->post(route('admin.returns.manual', $returnRequest), ['reference' => 'COD-CASH-1001']);

    Notification::assertSentTo($buyer, RefundOutcomeNotification::class, fn ($notification): bool => $notification->afterCommit === true && $notification->status === 'succeeded');
    Notification::assertSentTo($admin, RefundOutcomeNotification::class, fn ($notification): bool => $notification->afterCommit === true && $notification->operations);

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::Refunded);
});
