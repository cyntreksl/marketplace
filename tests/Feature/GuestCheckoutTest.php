<?php

use App\Models\Auction;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderAcknowledgmentNotification;
use App\Services\GuestOrderAccessService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/** @return array<string, string> */
function guestCheckoutAddress(string $email = 'guest@example.com'): array
{
    return [
        'email' => $email,
        'recipient_name' => 'Guest Buyer',
        'address_line_one' => '10 Main Road',
        'city' => 'Colombo',
        'postal_code' => '01000',
        'phone' => '0771234567',
    ];
}

/** @return array<string, mixed> */
function guestStripeSession(Payment $payment, string $state = 'open'): array
{
    return [
        'id' => 'cs_test_guest',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_guest',
        'expires_at' => now()->addMinutes(31)->timestamp,
        'metadata' => ['payment_id' => (string) $payment->id],
        'client_reference_id' => (string) $payment->customer_order_id,
        'currency' => 'lkr',
        'amount_total' => 260000,
        'payment_intent' => $state === 'paid' ? 'pi_test_guest' : null,
        'status' => $state === 'paid' ? 'complete' : 'open',
        'payment_status' => $state === 'paid' ? 'paid' : 'unpaid',
    ];
}

test('a guest can place a COD order once and access it only with the bearer token', function (): void {
    $listing = Listing::factory()->create(['price' => 1000, 'sale_price' => null, 'stock_quantity' => 5]);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2])->assertSessionHasNoErrors();

    $this->get(route('checkout.show'))->assertInertia(fn ($page) => $page
        ->where('isGuest', true)
        ->where('contactEmail', '')
        ->has('savedAddresses', 0));
    $this->post(route('checkout.store'), [...guestCheckoutAddress(), 'email' => null])->assertSessionHasErrors('email');
    $this->post(route('checkout.store'), guestCheckoutAddress('Guest@Example.com'))
        ->assertRedirect(route('checkout.payment.show', absolute: false));
    $this->post(route('checkout.payment.store'), ['payment_method' => 'cod'])
        ->assertRedirect(route('checkout.review.show', absolute: false));

    $review = checkoutReviewData();
    $response = $this->post(route('checkout.review.store'), $review)->assertRedirect();
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $accessToken = $query['access'] ?? null;
    $order = CustomerOrder::sole();

    expect($accessToken)->toBeString()
        ->and($order->buyer_id)->toBeNull()
        ->and($order->contact_email)->toBe('guest@example.com')
        ->and($order->marketing_opt_in)->toBeFalse()
        ->and($order->checkout_identity_hash)->toHaveLength(64)
        ->and($order->guest_access_token_hash)->toBe(hash('sha256', $accessToken))
        ->and(DB::table('customer_orders')->where('id', $order->id)->value('guest_access_token_hash'))->not->toBe($accessToken)
        ->and($listing->fresh()->reserved_quantity)->toBe(2);

    Notification::assertSentOnDemandTimes(OrderAcknowledgmentNotification::class, 1);
    Notification::assertSentOnDemand(
        OrderAcknowledgmentNotification::class,
        fn (OrderAcknowledgmentNotification $notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === ['guest@example.com' => 'Guest Buyer']
            && str_contains((string) $notification->claimUrl, 'signature='),
    );

    $this->post(route('checkout.review.store'), $review)->assertRedirect();
    expect(CustomerOrder::count())->toBe(1);
    Notification::assertSentOnDemandTimes(OrderAcknowledgmentNotification::class, 1);

    $this->flushSession();
    $this->get(route('checkout.thank_you.show', $order->number))->assertForbidden();
    $this->get(route('checkout.thank_you.show', ['customerOrder' => $order->number, 'access' => $accessToken]))
        ->assertInertia(fn ($page) => $page
            ->where('isGuestOrder', true)
            ->where('order.number', $order->number)
            ->where('shouldTrackPurchase', false));

    $this->postJson(route('order-tracking.store'), ['number' => $order->number, 'email' => 'GUEST@example.com'])
        ->assertOk()
        ->assertJsonPath('order.number', $order->number);
});

test('guest card payment uses the contact email and enforces access and expiry', function (): void {
    config(['services.stripe.secret' => 'sk_test_fake', 'services.stripe.webhook_secret' => 'whsec_fake']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.stripe.com/v1/checkout/sessions*' => fn () => Http::response(guestStripeSession(Payment::query()->latest('id')->firstOrFail()))]);
    $listing = Listing::factory()->create(['price' => 1000, 'sale_price' => null, 'stock_quantity' => 5]);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2]);
    $this->post(route('checkout.store'), [...guestCheckoutAddress('card@example.com'), 'marketing_opt_in' => '1'])->assertSessionHasNoErrors();
    $this->post(route('checkout.payment.store'), ['payment_method' => 'stripe'])->assertSessionHasNoErrors();

    $response = $this->post(route('checkout.review.store'), checkoutReviewData())
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_guest');
    $order = CustomerOrder::sole();
    $payment = Payment::sole();

    expect($order->buyer_id)->toBeNull()
        ->and($order->contact_email)->toBe('card@example.com')
        ->and($order->marketing_opt_in)->toBeTrue();

    $accessToken = null;
    Http::assertSent(function ($request) use (&$accessToken, $order, $payment): bool {
        parse_str((string) parse_url($request['success_url'], PHP_URL_QUERY), $query);
        $accessToken = $query['access'] ?? null;

        return $request['customer_email'] === 'card@example.com'
            && $request['metadata'] === [
                'payment_id' => (string) $payment->id,
                'customer_order_id' => (string) $order->id,
                'order_number' => $order->number,
            ]
            && $accessToken !== null
            && str_contains($request['cancel_url'], 'access=');
    });

    $this->flushSession();
    $this->post(route('checkout.card.retry', $order->number))->assertForbidden();
    $this->post(route('checkout.card.retry', ['customerOrder' => $order->number, 'access' => $accessToken]))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_guest');

    $this->travel(31)->minutes();
    $this->post(route('checkout.card.retry', ['customerOrder' => $order->number, 'access' => $accessToken]))
        ->assertStatus(410);
});

test('a seven day signed claim attaches only to a verified matching account', function (): void {
    $rawAccessToken = 'guest-order-access-token';
    $order = CustomerOrder::factory()->guest()->create([
        'contact_email' => 'claim@example.com',
        'guest_access_token_hash' => hash('sha256', $rawAccessToken),
    ]);
    $claimUrl = app(GuestOrderAccessService::class)->claimUrl($order);
    $matchingBuyer = User::factory()->create(['email' => 'claim@example.com']);

    $this->actingAs($matchingBuyer)->get($claimUrl)
        ->assertRedirect(route('buyer.orders.show', $order->number));

    expect($order->fresh()->buyer_id)->toBe($matchingBuyer->id)
        ->and($order->fresh()->guest_access_token_hash)->toBeNull()
        ->and($order->fresh()->checkout_identity_hash)->toBeNull();

    $privateOrder = CustomerOrder::factory()->guest()->create(['contact_email' => 'private@example.com']);
    $this->actingAs(User::factory()->create(['email' => 'different@example.com']))
        ->get(app(GuestOrderAccessService::class)->claimUrl($privateOrder))
        ->assertForbidden();
    expect($privateOrder->fresh()->buyer_id)->toBeNull();

    $expiringOrder = CustomerOrder::factory()->guest()->create(['contact_email' => 'claim@example.com']);
    $expiredUrl = app(GuestOrderAccessService::class)->claimUrl($expiringOrder);
    $this->travel(8)->days();
    $this->actingAs($matchingBuyer)->get($expiredUrl)->assertForbidden();
});

test('guest checkout does not make auction bidding public', function (): void {
    $auction = Auction::factory()->create();

    $this->post(route('auctions.bids.store', $auction), ['amount' => 1000])
        ->assertRedirect(route('login'));
});
