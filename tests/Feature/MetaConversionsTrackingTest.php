<?php

use App\Contracts\MetaConversionsGateway;
use App\Jobs\SendMetaConversion;
use App\Jobs\SendMetaPurchase;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\User;
use App\Services\MetaConversionsService;
use App\Support\MetaConversionEvent;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'services.meta_conversions.enabled' => true,
        'services.meta_conversions.pixel_id' => '2154698912092970',
        'services.meta_conversions.access_token' => 'test-access-token',
        'services.meta_conversions.api_version' => 'v25.0',
    ]);
});

test('valid public listing views queue ViewContent while crawlers and prefetches do not', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create(['price' => '2500.00', 'sale_price' => null]);

    $this->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug).'?fbclid=click_123')
        ->assertOk();

    Queue::assertPushed(SendMetaConversion::class, function (SendMetaConversion $job) use ($listing): bool {
        return $job->event->name === 'ViewContent'
            && $job->event->customData['content_ids'] === [(string) $listing->id]
            && $job->event->customData['value'] === '2500.00'
            && str_starts_with($job->event->userData['fbc'], 'fb.1.')
            && ! array_key_exists('fbclid', $job->event->userData);
    });

    Queue::fake();
    $this->withHeader('User-Agent', 'Googlebot')->get(route('listings.show', $listing->slug))->assertOk();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Purpose' => 'prefetch'])->get(route('listings.show', $listing->slug))->assertOk();
    Queue::assertNothingPushed();
});

test('successful cart additions queue the added quantity and invalid mutations do not', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null, 'stock_quantity' => 5]);

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])->assertSessionHasNoErrors();

    Queue::assertPushed(SendMetaConversion::class, 2);
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->name === 'AddToCart'
        && $job->event->customData['contents'][0]['quantity'] === 1
        && $job->event->customData['value'] === '1000.00');

    Queue::fake();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 100])->assertSessionHasErrors('quantity');
    Queue::assertNothingPushed();
});

test('checkout queues only for a non-empty valid cart and disabled tracking is silent', function (): void {
    Queue::fake();
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('checkout.show'))->assertOk();
    Queue::assertNothingPushed();

    $listing = Listing::factory()->create();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1]);
    Queue::fake();
    $this->get(route('checkout.show'))->assertOk();
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->name === 'InitiateCheckout');

    config(['services.meta_conversions.enabled' => false]);
    Queue::fake();
    $this->get(route('checkout.show'))->assertOk();
    Queue::assertNothingPushed();
});

test('confirmed COD orders queue Purchase and store attribution encrypted', function (): void {
    Queue::fake();
    $buyer = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Buyer Person']);
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null]);

    $this->actingAs($buyer)
        ->withCookie('_fbp', 'fb.1.123.browser')
        ->withHeader('User-Agent', 'Checkout Browser')
        ->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2]);
    $this->post(route('checkout.store'), [
        'recipient_name' => 'Buyer Person',
        'address_line_one' => '10 Main Road',
        'city' => 'Colombo',
        'postal_code' => '01000',
        'phone' => '0771234567',
    ]);
    $this->post(route('checkout.payment.store'), ['payment_method' => 'cod']);
    $review = checkoutReviewData();
    Queue::fake();

    $this->withCookie('_fbp', 'fb.1.123.browser')
        ->withHeader('User-Agent', 'Checkout Browser')
        ->post(route('checkout.review.store'), $review)
        ->assertRedirect();

    $order = CustomerOrder::sole();
    $rawAttribution = DB::table('customer_orders')->where('id', $order->id)->value('meta_attribution');
    expect($order->fresh()->meta_attribution['fbp'])->toBe('fb.1.123.browser')
        ->and($rawAttribution)->not->toContain('Checkout Browser')
        ->and($rawAttribution)->not->toContain('fb.1.123.browser');
    Queue::assertPushed(SendMetaPurchase::class, 1);
});

test('Purchase uses a stable event id hashes PII and clears accepted attribution', function (): void {
    Queue::fake();
    $buyer = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Buyer Person']);
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null]);

    $this->actingAs($buyer)->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1]);
    $this->post(route('checkout.store'), [
        'recipient_name' => 'Buyer Person',
        'address_line_one' => '10 Main Road',
        'city' => 'Colombo',
        'postal_code' => '01000',
        'phone' => '0771234567',
    ]);
    $this->post(route('checkout.payment.store'), ['payment_method' => 'cod']);
    $review = checkoutReviewData();
    Queue::fake();
    $this->post(route('checkout.review.store'), $review);
    $order = CustomerOrder::sole();

    $gateway = new class implements MetaConversionsGateway
    {
        public ?MetaConversionEvent $event = null;

        public function send(MetaConversionEvent $event, ?string $testEventCode = null): void
        {
            $this->event = $event;
        }
    };
    app()->instance(MetaConversionsGateway::class, $gateway);
    app(MetaConversionsService::class)->sendPurchase($order->id);

    $serialized = json_encode($gateway->event?->toArray(), JSON_THROW_ON_ERROR);
    expect($gateway->event?->id)->toBe('Purchase:'.$order->number)
        ->and($gateway->event?->customData['order_id'])->toBe($order->number)
        ->and($gateway->event?->userData['em'][0])->toBe(hash('sha256', 'buyer@example.com'))
        ->and($gateway->event?->userData['ph'][0])->toBe(hash('sha256', '94771234567'))
        ->and($serialized)->not->toContain('buyer@example.com')
        ->and($serialized)->not->toContain('0771234567')
        ->and($order->fresh()->meta_attribution)->toBeNull();
});

test('queued jobs are unique and use bounded retry settings', function (): void {
    $event = new MetaConversionEvent('ViewContent', 'event-unique', now()->timestamp, 'https://prodeals.lk/', [], []);
    $eventJob = new SendMetaConversion($event);
    $purchaseJob = new SendMetaPurchase(42);

    expect($eventJob->uniqueId())->toBe('event-unique')
        ->and($eventJob)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($purchaseJob->uniqueId())->toBe('Purchase:42')
        ->and($eventJob->tries)->toBe(5)
        ->and($eventJob->backoff)->toBe([10, 60, 300, 900])
        ->and($purchaseJob->tries)->toBe(5)
        ->and($purchaseJob->timeout)->toBe(15);
});

test('commerce still succeeds when a synchronous Meta delivery fails', function (): void {
    app()->instance(MetaConversionsGateway::class, new class implements MetaConversionsGateway
    {
        public function send(MetaConversionEvent $event, ?string $testEventCode = null): void
        {
            throw new RuntimeException('Simulated Meta outage');
        }
    });
    $listing = Listing::factory()->create();

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('cart_added', true);
});

test('synthetic command requires a temporary code and never displays credentials', function (): void {
    $gateway = new class implements MetaConversionsGateway
    {
        public ?MetaConversionEvent $event = null;

        public ?string $testEventCode = null;

        public function send(MetaConversionEvent $event, ?string $testEventCode = null): void
        {
            $this->event = $event;
            $this->testEventCode = $testEventCode;
        }
    };
    app()->instance(MetaConversionsGateway::class, $gateway);

    $this->artisan('meta:conversions:test')->assertFailed();
    $this->artisan('meta:conversions:test', ['--test-event-code' => 'TEST123'])
        ->expectsOutputToContain('Meta accepted')
        ->doesntExpectOutput('test-access-token')
        ->assertSuccessful();

    expect($gateway->event?->name)->toBe('ViewContent')
        ->and($gateway->event?->userData['em'][0])->toBe(hash('sha256', 'meta-test@prodeals.lk'))
        ->and(json_encode($gateway->event?->toArray(), JSON_THROW_ON_ERROR))->not->toContain('meta-test@prodeals.lk')
        ->and($gateway->testEventCode)->toBe('TEST123');
});
