<?php

use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['services.stripe.secret' => 'sk_test_fake', 'services.stripe.webhook_secret' => 'whsec_fake']);
    Notification::fake();
    Http::preventStrayRequests();
});

/** @return array{0: User, 1: Listing, 2: array{checkout_token: string, review_hash: string}} */
function prepareCardOrder(string $method = 'stripe'): array
{
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 1000, 'sale_price' => null, 'stock_quantity' => 5]);
    test()->actingAs($buyer)->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2])->assertSessionHasNoErrors();
    test()->post(route('checkout.store'), ['recipient_name' => 'Buyer', 'address_line_one' => '10 Main Road', 'city' => 'Colombo', 'phone' => '0771234567'])->assertSessionHasNoErrors();
    test()->post(route('checkout.payment.store'), ['payment_method' => $method])->assertSessionHasNoErrors();

    return [$buyer, $listing, checkoutReviewData()];
}

/** @return array<string, mixed> */
function stripeSession(Payment $payment, string $state = 'paid'): array
{
    return ['id' => 'cs_test_checkout', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout', 'expires_at' => now()->addMinutes(31)->timestamp,
        'metadata' => ['payment_id' => (string) $payment->id], 'client_reference_id' => (string) $payment->customer_order_id,
        'currency' => 'lkr', 'amount_total' => 260000, 'payment_intent' => 'pi_test_paid',
        'status' => $state === 'paid' ? 'complete' : ($state === 'expired' ? 'expired' : 'open'), 'payment_status' => $state === 'paid' ? 'paid' : 'unpaid'];
}

function fakeStripeCheckout(string $state = 'open'): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/v1/checkout/sessions*' => fn () => Http::response(stripeSession(Payment::query()->latest('id')->firstOrFail(), $state))]);
}

function sendStripeEvent(Payment $payment, string $type = 'checkout.session.completed'): TestResponse
{
    $body = json_encode(['id' => 'evt_test', 'type' => $type, 'data' => ['object' => stripeSession($payment)]], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_fake');

    return test()->call('POST', route('webhooks.stripe'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
}

test('card checkout redirects to hosted payment and duplicate placement reuses the order', function (): void {
    [$buyer, $listing, $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review)->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');
    $order = CustomerOrder::sole();
    $this->post(route('checkout.review.store'), $review)->assertRedirect(route('checkout.thank_you.show', $order->number));
    expect(CustomerOrder::count())->toBe(1)->and(Payment::count())->toBe(1)->and($listing->fresh()->reserved_quantity)->toBe(2)->and($order->total)->toBe('2600.00')->and($order->shipping_total)->toBe('600.00');
    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key') && $request['line_items'][0]['price_data']['unit_amount'] === 260000 && $request['payment_method_types'] === ['card']);
});

test('verified card payment confirms seller orders and notifies once', function (): void {
    [, $listing, $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $payment = Payment::sole();
    fakeStripeCheckout('paid');
    sendStripeEvent($payment)->assertNoContent();
    sendStripeEvent($payment)->assertNoContent();
    $this->get(route('checkout.card.return', $payment->customerOrder->number))->assertRedirect();
    expect($payment->fresh()->status)->toBe('paid')->and($payment->fresh()->provider_reference)->toBe('pi_test_paid')->and($payment->customerOrder->fresh()->status)->toBe('confirmed')->and($payment->customerOrder->sellerOrders()->sole()->status)->toBe('paid')->and($listing->fresh()->reserved_quantity)->toBe(2);
    Notification::assertSentTimes(PaymentConfirmedNotification::class, 1);
});

test('canceled or declined card checkout remains retryable without a new order', function (): void {
    [, , $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $order = CustomerOrder::sole();
    $this->get(route('checkout.card.return', $order->number))->assertRedirect();
    $this->post(route('checkout.card.retry', $order->number))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');
    expect($order->fresh()->status)->toBe('pending_payment')->and(Payment::sole()->status)->toBe('pending')->and(CustomerOrder::count())->toBe(1);
    Notification::assertNotSentTo($order->buyer, PaymentConfirmedNotification::class);
});

test('payment creation timeouts retain a saved order for retry', function (): void {
    [, $listing, $review] = prepareCardOrder();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/*' => Http::failedConnection()]);
    $this->post(route('checkout.review.store'), $review)->assertSessionHasErrors('payment');
    expect(CustomerOrder::count())->toBe(1)->and($listing->fresh()->reserved_quantity)->toBe(2);
    fakeStripeCheckout();
    $this->post(route('checkout.card.retry', CustomerOrder::sole()->number))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');
    expect(CustomerOrder::count())->toBe(1);
});

test('unpaid expired sessions release stock exactly once', function (): void {
    [, $listing, $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $payment = Payment::sole();
    fakeStripeCheckout('expired');
    sendStripeEvent($payment, 'checkout.session.expired')->assertNoContent();
    sendStripeEvent($payment, 'checkout.session.expired')->assertNoContent();
    expect($listing->fresh()->reserved_quantity)->toBe(0)->and($payment->fresh()->status)->toBe('expired')->and($payment->customerOrder->fresh()->status)->toBe('expired');
});

test('reconciliation preserves stock on provider outages and confirms delayed payments', function (): void {
    [, $listing, $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    Payment::sole()->update(['expires_at' => now()->subMinute()]);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/*' => Http::failedConnection()]);
    $this->artisan('checkout:reconcile-payments')->assertFailed();
    expect($listing->fresh()->reserved_quantity)->toBe(2)->and(Payment::sole()->status)->toBe('pending');
    fakeStripeCheckout('paid');
    $this->artisan('checkout:reconcile-payments')->assertSuccessful();
    expect(Payment::sole()->status)->toBe('paid');
});

test('callbacks reject invalid signatures and mismatched payment details', function (string $field, mixed $value): void {
    [, , $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $payment = Payment::sole();
    $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'whsec_fake'])->assertBadRequest();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/*' => Http::response(array_replace(stripeSession($payment), [$field => $value]))]);
    sendStripeEvent($payment)->assertBadRequest();
    expect($payment->fresh()->status)->toBe('pending');
})->with([['amount_total', 1], ['currency', 'usd'], ['client_reference_id', '999'], ['metadata', ['payment_id' => '999']]]);

test('changed prices require another review and do not reserve stock', function (): void {
    [, $listing, $review] = prepareCardOrder('cod');
    $listing->update(['price' => 1200]);
    $this->post(route('checkout.review.store'), $review)->assertSessionHasErrors('cart');
    expect(CustomerOrder::count())->toBe(0)->and($listing->fresh()->reserved_quantity)->toBe(0);
    $this->post(route('checkout.review.store'), checkoutReviewData())->assertSessionHasNoErrors();
    expect(CustomerOrder::sole()->total)->toBe('3000.00');
});

test('bank transfer is rejected and unconfigured Stripe cannot place an order', function (): void {
    [, , $review] = prepareCardOrder();
    $this->post(route('checkout.payment.store'), ['payment_method' => 'bank_transfer'])->assertSessionHasErrors('payment_method');
    config(['services.stripe.secret' => null]);
    $this->post(route('checkout.review.store'), $review)->assertSessionHasErrors('payment_method');
    expect(CustomerOrder::count())->toBe(0);
});

test('payment recovery is restricted to the order owner', function (): void {
    [, , $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $order = CustomerOrder::sole();
    $this->actingAs(User::factory()->create())->post(route('checkout.card.retry', $order->number))->assertForbidden();
    $this->get(route('checkout.card.return', $order->number))->assertForbidden();
});

test('the thirty minute deadline expires the hosted session before releasing stock', function (): void {
    [, $listing, $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $payment = Payment::sole();
    $this->travel(31)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/*' => fn ($request) => Http::response(stripeSession($payment, str_ends_with($request->url(), '/expire') ? 'expired' : 'open'))]);
    $this->artisan('checkout:reconcile-payments')->assertSuccessful();
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/expire') && $request->method() === 'POST');
    expect($payment->fresh()->status)->toBe('expired')->and($listing->fresh()->reserved_quantity)->toBe(0);
});

test('payment winning the expiration race remains confirmed', function (): void {
    [, $listing, $review] = prepareCardOrder();
    fakeStripeCheckout();
    $this->post(route('checkout.review.store'), $review);
    $payment = Payment::sole();
    $this->travel(31)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/*' => Http::sequence()->push(stripeSession($payment, 'open'))->push(['error' => ['message' => 'Already complete']], 400)->push(stripeSession($payment, 'paid'))]);
    $this->artisan('checkout:reconcile-payments')->assertSuccessful();
    expect($payment->fresh()->status)->toBe('paid')->and($listing->fresh()->reserved_quantity)->toBe(2);
});
