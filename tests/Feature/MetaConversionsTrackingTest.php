<?php

use App\Contracts\MetaConversionsGateway;
use App\Jobs\SendMetaConversion;
use App\Jobs\SendMetaPurchase;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\User;
use App\Services\MetaConversionsService;
use App\Services\MetaParameterBuilderService;
use App\Services\MetaTestSessionService;
use App\Support\MetaConversionEvent;
use App\Support\MetaConversionReceipt;
use App\Support\TrackingConsent;
use FacebookAds\ParamBuilder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config([
        'services.meta_conversions.enabled' => true,
        'services.meta_conversions.pixel_id' => '2154698912092970',
        'services.meta_conversions.access_token' => 'test-access-token',
        'services.meta_conversions.api_version' => 'v25.0',
    ]);
});

function marketingConsentCookie(): string
{
    return urlencode(json_encode([
        'version' => TrackingConsent::VERSION,
        'analytics' => false,
        'marketing' => true,
        'decidedAt' => '2026-09-07T10:00:00.000Z',
    ], JSON_THROW_ON_ERROR));
}

test('valid public listing views queue ViewContent while crawlers and prefetches do not', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create(['price' => '2500.00', 'sale_price' => null]);
    config(['session.domain' => 'prodeals.lk']);
    $clickId = 'AbC_def-123_XyZ';

    $listingUrl = 'https://prodeals.lk'.route('listings.show', $listing->slug, absolute: false);
    $requestStartedAt = now();

    $response = $this->withHeader('User-Agent', 'Mozilla/5.0')
        ->get($listingUrl.'?fbclid='.$clickId)
        ->assertOk()
        ->assertCookieNotExpired('_fbc');

    $cookie = $response->getCookie('_fbc', false);
    $fbc = $cookie?->getValue();
    expect($fbc)->toMatch('/^fb\.\d+\.\d{13}\.'.$clickId.'\.[A-Za-z0-9_-]{8}$/')
        ->and($cookie?->getExpiresTime())->toBeGreaterThanOrEqual($requestStartedAt->addDays(90)->timestamp)
        ->and($cookie?->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(90)->timestamp)
        ->and($cookie?->getPath())->toBe('/')
        ->and($cookie?->getDomain())->toBe('prodeals.lk')
        ->and($cookie?->isSecure())->toBeTrue()
        ->and($cookie?->isHttpOnly())->toBeFalse()
        ->and($cookie?->getSameSite())->toBe('lax');

    Queue::assertPushed(SendMetaConversion::class, function (SendMetaConversion $job) use ($fbc, $listing): bool {
        return $job->event->name === 'ViewContent'
            && $job->event->customData['content_ids'] === [(string) $listing->id]
            && $job->event->customData['value'] === 2500.0
            && $job->event->userData['fbc'] === $fbc
            && ! array_key_exists('fbp', $job->event->userData)
            && ! array_key_exists('fbclid', $job->event->userData);
    });
    $viewContentEventId = null;
    Queue::assertPushed(SendMetaConversion::class, function (SendMetaConversion $job) use (&$viewContentEventId): bool {
        $viewContentEventId = $job->event->id;

        return $job->event->name === 'ViewContent';
    });
    expect($viewContentEventId)->toBeString();
    $response->assertInertia(fn ($page) => $page->where('metaEventId', $viewContentEventId));

    Queue::fake();
    $this->withHeader('User-Agent', 'Googlebot')->get(route('listings.show', $listing->slug))->assertOk();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Purpose' => 'prefetch'])->get(route('listings.show', $listing->slug))->assertOk();
    Queue::assertNothingPushed();
});

test('legacy Meta cookies are upgraded with builder appendices', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();
    $fbc = 'fb.1.1788775200123.ExistingClick-ID';
    $fbp = 'fb.1.1788775200123.1116446470';
    $expectedFbc = $fbc.'.AQEAAQMB';
    $expectedFbp = $fbp.'.AQEAAQMB';

    $this->withUnencryptedCookies([
        '_fbc' => $fbc,
        '_fbp' => $fbp,
        TrackingConsent::COOKIE_NAME => marketingConsentCookie(),
    ])
        ->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug).'?fbclid=ExistingClick-ID')
        ->assertOk()
        ->assertPlainCookie('_fbc', $expectedFbc)
        ->assertPlainCookie('_fbp', $expectedFbp);

    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->userData['fbc'] === $expectedFbc
        && $job->event->userData['fbp'] === $expectedFbp);
});

test('appended Meta cookies remain plaintext and an unchanged click id is preserved', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();
    $fbc = 'fb.1.1788775200123.ExistingClick-ID.AQEAAQMB';
    $fbp = 'fb.1.1788775200123.1116446470.AQEAAQMB';

    $this->withUnencryptedCookies([
        '_fbc' => $fbc,
        '_fbp' => $fbp,
        TrackingConsent::COOKIE_NAME => marketingConsentCookie(),
    ])
        ->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug).'?fbclid=ExistingClick-ID')
        ->assertOk()
        ->assertCookieMissing('_fbc')
        ->assertCookieMissing('_fbp');

    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->userData['fbc'] === $fbc
        && $job->event->userData['fbp'] === $fbp);
});

test('a newer Meta click replaces stored attribution and preserves case', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();
    $existingFbc = 'fb.1.1788775200123.OldClick.AQEAAQMB';
    $newClickId = 'NewClick_AbC-123';

    $response = $this->withUnencryptedCookie('_fbc', $existingFbc)
        ->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug).'?fbclid='.$newClickId)
        ->assertOk();

    $newFbc = $response->getCookie('_fbc', false)?->getValue();
    expect($newFbc)->toMatch('/^fb\.\d+\.\d{13}\.'.$newClickId.'\.[A-Za-z0-9_-]{8}$/');

    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->userData['fbc'] === $newFbc);
});

test('dotted Meta click ids remain exact across the landing request and later events', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();
    $clickId = 'IwAR3xYz.AbC_123-test';

    $response = $this->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug).'?fbclid='.$clickId)
        ->assertOk();

    $fbc = $response->getCookie('_fbc', false)?->getValue();
    expect($fbc)->toMatch('/^fb\.\d+\.\d{13}\.IwAR3xYz\.AbC_123-test\.[A-Za-z0-9_-]{8}$/');
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->userData['fbc'] === $fbc);

    Queue::fake();
    $this->withUnencryptedCookie('_fbc', $fbc)
        ->withHeader('User-Agent', 'Mozilla/5.0')
        ->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->name === 'AddToCart'
        && $job->event->userData['fbc'] === $fbc);
});

test('a valid click id in the referrer creates fbc and propagates builder request data', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();
    $referrer = 'https://www.facebook.com/ad?fbclid=ReferrerClick_ABC';

    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0',
        'Referer' => $referrer,
        'X-Forwarded-For' => '8.8.8.8, 10.0.0.1',
    ])->get(route('listings.show', $listing->slug))->assertOk();

    $fbc = $response->getCookie('_fbc', false)?->getValue();
    expect($fbc)->toMatch('/^fb\.\d+\.\d{13}\.ReferrerClick_ABC\.[A-Za-z0-9_-]{8}$/');
    Queue::assertPushed(SendMetaConversion::class, function (SendMetaConversion $job) use ($fbc, $listing, $referrer): bool {
        return $job->event->userData['fbc'] === $fbc
            && str_starts_with($job->event->userData['client_ip_address'], '8.8.8.8.')
            && str_starts_with($job->event->sourceUrl, route('listings.show', $listing->slug))
            && str_starts_with($job->event->referrerUrl ?? '', $referrer.'.');
    });
});

test('invalid Meta click ids and ordinary traffic do not create attribution cookies', function (mixed $clickId): void {
    $url = route('home');

    if ($clickId !== null) {
        $url .= '?'.http_build_query(['fbclid' => $clickId]);
    }

    $this->get($url)
        ->assertOk()
        ->assertCookieMissing('_fbc')
        ->assertCookieMissing('_fbp');
})->with([
    'no Meta click' => null,
    'unsafe characters' => 'bad click!',
    'oversized value' => str_repeat('A', 501),
    'non-scalar value' => [['click-id']],
]);

test('invalid existing fbc values are ignored', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();

    $this->withUnencryptedCookie('_fbc', 'not-a-valid-fbc')
        ->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertCookieMissing('_fbc');

    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => ! array_key_exists('fbc', $job->event->userData));
});

test('fbp is set and sent only after marketing consent', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create();
    $existingFbp = 'fb.1.1788775200123.1116446470.AQEAAQMB';

    $this->withUnencryptedCookie('_fbp', $existingFbp)
        ->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertCookieMissing('_fbp');
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => ! array_key_exists('fbp', $job->event->userData));

    Queue::fake();
    $response = $this->withUnencryptedCookies([
        '_fbp' => '',
        TrackingConsent::COOKIE_NAME => marketingConsentCookie(),
    ])->withHeader('User-Agent', 'Mozilla/5.0')
        ->get(route('listings.show', $listing->slug))
        ->assertOk();

    $fbp = $response->getCookie('_fbp', false)?->getValue();
    expect($fbp)->toMatch('/^fb\.\d+\.\d{13}\.\d+\.[A-Za-z0-9_-]{8}$/');
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->userData['fbp'] === $fbp);
});

test('builder-formatted customer data is hashed exactly once', function (): void {
    $builder = app(MetaParameterBuilderService::class);
    $hash = hash('sha256', 'buyer@example.com');
    $formatted = $builder->normalizedAndHashedPii(' Buyer@Example.COM ', MetaParameterBuilderService::PII_EMAIL);
    $preHashed = $builder->normalizedAndHashedPii($hash, MetaParameterBuilderService::PII_EMAIL);

    expect($formatted)->toMatch('/^'.$hash.'\.[A-Za-z0-9_-]{8}$/')
        ->and($preHashed)->toMatch('/^'.$hash.'\.[A-Za-z0-9_-]{8}$/')
        ->and($builder->normalizedAndHashedPii($preHashed, MetaParameterBuilderService::PII_EMAIL))->toBe($preHashed);
});

test('successful cart additions queue the added quantity and invalid mutations do not', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null, 'stock_quantity' => 5]);
    $fbc = 'fb.1.1788775200123.CartClick.AQEAAQMB';
    $fbp = 'fb.1.1788775200123.1116446470.AQEAAQMB';

    $response = $this->withUnencryptedCookies([
        '_fbc' => $fbc,
        '_fbp' => $fbp,
        TrackingConsent::COOKIE_NAME => marketingConsentCookie(),
    ])
        ->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2])
        ->assertSessionHasNoErrors();
    $addToCartEventId = null;
    Queue::assertPushed(SendMetaConversion::class, function (SendMetaConversion $job) use (&$addToCartEventId): bool {
        if ($job->event->name !== 'AddToCart' || $job->event->customData['contents'][0]['quantity'] !== 2) {
            return false;
        }

        $addToCartEventId = $job->event->id;

        return true;
    });
    expect($addToCartEventId)->toBeString();
    $response->assertSessionHas('meta_event_id', $addToCartEventId);
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('commerce.meta_event_id', $addToCartEventId));

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])->assertSessionHasNoErrors();

    Queue::assertPushed(SendMetaConversion::class, 2);
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->name === 'AddToCart'
        && $job->event->customData['contents'][0]['quantity'] === 1
        && $job->event->customData['value'] === 1000.0
        && $job->event->userData['fbc'] === $fbc
        && $job->event->userData['fbp'] === $fbp);

    Queue::fake();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 100])->assertSessionHasErrors('quantity');
    Queue::assertNothingPushed();
});

test('checkout queues only for a non-empty valid cart and disabled tracking is silent', function (): void {
    Queue::fake();
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('metaEventId', null));
    Queue::assertNothingPushed();

    $listing = Listing::factory()->create();
    $fbc = 'fb.1.1788775200123.CheckoutClick.AQEAAQMB';
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1]);
    Queue::fake();
    $response = $this->withUnencryptedCookie('_fbc', $fbc)->get(route('checkout.show'))->assertOk();
    $initiateCheckoutEventId = null;
    Queue::assertPushed(SendMetaConversion::class, fn (SendMetaConversion $job): bool => $job->event->name === 'InitiateCheckout'
        && $job->event->userData['fbc'] === $fbc);
    Queue::assertPushed(SendMetaConversion::class, function (SendMetaConversion $job) use (&$initiateCheckoutEventId): bool {
        $initiateCheckoutEventId = $job->event->id;

        return $job->event->name === 'InitiateCheckout';
    });
    expect($initiateCheckoutEventId)->toBeString();
    $response->assertInertia(fn ($page) => $page->where('metaEventId', $initiateCheckoutEventId));

    config(['services.meta_conversions.enabled' => false]);
    Queue::fake();
    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('metaEventId', null));
    Queue::assertNothingPushed();
});

test('confirmed COD orders queue Purchase and store attribution encrypted', function (): void {
    Queue::fake();
    $buyer = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Buyer Person']);
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null]);
    $fbc = 'fb.1.1788775200123.PurchaseClick.AQEAAQMB';
    $fbp = 'fb.1.1788775200123.1116446470.AQEAAQMB';

    $this->actingAs($buyer)
        ->withUnencryptedCookies([
            '_fbc' => $fbc,
            '_fbp' => $fbp,
            TrackingConsent::COOKIE_NAME => marketingConsentCookie(),
        ])
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

    $this->withHeader('User-Agent', 'Checkout Browser')
        ->post(route('checkout.review.store'), $review)
        ->assertRedirect();

    $order = CustomerOrder::sole();
    $rawAttribution = DB::table('customer_orders')->where('id', $order->id)->value('meta_attribution');
    expect($order->fresh()->meta_attribution['fbc'])->toBe($fbc)
        ->and($order->fresh()->meta_attribution['fbp'])->toBe($fbp)
        ->and($rawAttribution)->not->toContain('Checkout Browser')
        ->and($rawAttribution)->not->toContain($fbc)
        ->and($rawAttribution)->not->toContain($fbp);
    Queue::assertPushed(SendMetaPurchase::class, 1);
});

test('Purchase retries with a stable event id and atomically records Meta acceptance', function (): void {
    Queue::fake();
    $buyer = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Buyer Person']);
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null]);
    $fbc = 'fb.1.1788775200123.CompletedPurchaseClick.AQEAAQMB';

    $this->actingAs($buyer)
        ->withUnencryptedCookie('_fbc', $fbc)
        ->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1]);
    $this->post(route('checkout.store'), [
        'recipient_name' => 'Buyer Person',
        'address_line_one' => '10 Main Road',
        'city' => 'Colombo',
        'postal_code' => '01000',
        'phone' => '0771234567',
    ]);
    $this->post(route('checkout.payment.store'), ['payment_method' => 'cod']);
    $review = checkoutReviewData();
    $referrer = 'https://www.facebook.com/prodeals-ad';
    Queue::fake();
    $this->get(app(MetaTestSessionService::class)->createCheckoutLink('TEST123'))
        ->assertRedirect(route('home'));
    $this->withHeader('Referer', $referrer)->post(route('checkout.review.store'), $review);
    $order = CustomerOrder::sole();
    $rawAttribution = DB::table('customer_orders')->where('id', $order->id)->value('meta_attribution');
    expect($order->meta_attribution['test_event_code'])->toBe('TEST123')
        ->and($rawAttribution)->not->toContain('TEST123');

    $gateway = new class implements MetaConversionsGateway
    {
        /** @var list<MetaConversionEvent> */
        public array $events = [];

        /** @var list<string|null> */
        public array $testEventCodes = [];

        public bool $shouldFail = true;

        public function send(MetaConversionEvent $event, ?string $testEventCode = null): MetaConversionReceipt
        {
            $this->events[] = $event;
            $this->testEventCodes[] = $testEventCode;

            if ($this->shouldFail) {
                throw new RuntimeException('Simulated ambiguous Meta response');
            }

            return new MetaConversionReceipt(1, 'trace_purchase_123', []);
        }
    };
    app()->instance(MetaConversionsGateway::class, $gateway);

    expect(fn () => app(MetaConversionsService::class)->sendPurchase($order->id))
        ->toThrow(RuntimeException::class, 'Simulated ambiguous Meta response');
    expect($order->fresh()->meta_attribution)->not->toBeNull()
        ->and($order->fresh()->meta_purchase_sent_at)->toBeNull()
        ->and($order->fresh()->meta_purchase_trace_id)->toBeNull();

    $gateway->shouldFail = false;
    app(MetaConversionsService::class)->sendPurchase($order->id);
    app(MetaConversionsService::class)->sendPurchase($order->id);

    $event = $gateway->events[1];
    $serialized = json_encode($event->toArray(), JSON_THROW_ON_ERROR);
    expect($gateway->events)->toHaveCount(2)
        ->and($gateway->events[0]->id)->toBe($event->id)
        ->and($gateway->testEventCodes)->toBe(['TEST123', 'TEST123'])
        ->and($event->id)->toBe('Purchase:'.$order->number)
        ->and($event->customData['order_id'])->toBe($order->number)
        ->and($event->customData['currency'])->toBe('LKR')
        ->and($event->customData['value'])->toBe(1600.0)
        ->and($event->customData['contents'][0]['item_price'])->toBe(1000.0)
        ->and($event->sourceUrl)->toStartWith(route('checkout.review.store'))
        ->and($event->referrerUrl)->toStartWith($referrer.'.')
        ->and($event->userData['em'][0])->toMatch('/^'.hash('sha256', 'buyer@example.com').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['external_id'][0])->toMatch('/^'.hash('sha256', (string) $buyer->id).'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['ph'][0])->toMatch('/^'.hash('sha256', '94771234567').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['fn'][0])->toMatch('/^'.hash('sha256', 'buyer').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['ln'][0])->toMatch('/^'.hash('sha256', 'person').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['ct'][0])->toMatch('/^'.hash('sha256', 'colombo').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['zp'][0])->toMatch('/^'.hash('sha256', '01000').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['country'][0])->toMatch('/^'.hash('sha256', 'lk').'\.[A-Za-z0-9_-]{8}$/')
        ->and($event->userData['fbc'])->toBe($fbc)
        ->and($serialized)->not->toContain('buyer@example.com')
        ->and($serialized)->not->toContain('0771234567')
        ->and($order->fresh()->meta_attribution)->toBeNull()
        ->and($order->fresh()->meta_purchase_sent_at)->not->toBeNull()
        ->and($order->fresh()->meta_purchase_trace_id)->toBe('trace_purchase_123');
});

test('guest Purchase hashes the order contact email without an external id', function (): void {
    Queue::fake();
    $listing = Listing::factory()->create(['price' => '1000.00', 'sale_price' => null]);

    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1]);
    $this->post(route('checkout.store'), [
        'email' => 'Guest.Buyer@example.com',
        'recipient_name' => 'Guest Buyer',
        'address_line_one' => '10 Main Road',
        'city' => 'Colombo',
        'postal_code' => '01000',
        'phone' => '0771234567',
    ]);
    $this->post(route('checkout.payment.store'), ['payment_method' => 'cod']);
    $this->post(route('checkout.review.store'), checkoutReviewData());

    $order = CustomerOrder::sole();
    $order->forceFill([
        'meta_attribution' => ['source_url' => route('checkout.review.store')],
    ])->save();
    $gateway = new class implements MetaConversionsGateway
    {
        public ?MetaConversionEvent $event = null;

        public function send(MetaConversionEvent $event, ?string $testEventCode = null): MetaConversionReceipt
        {
            $this->event = $event;

            return new MetaConversionReceipt(1, null, []);
        }
    };
    app()->instance(MetaConversionsGateway::class, $gateway);

    app(MetaConversionsService::class)->sendPurchase($order->id);

    expect($gateway->event?->userData['em'][0])->toMatch('/^'.hash('sha256', 'guest.buyer@example.com').'\.[A-Za-z0-9_-]{8}$/')
        ->and($gateway->event?->userData)->not->toHaveKey('external_id')
        ->and($gateway->event?->customData['currency'])->toBe('LKR')
        ->and($gateway->event?->customData['value'])->toBeFloat()
        ->and($gateway->event?->customData['contents'][0]['id'])->toBe((string) $listing->id)
        ->and($gateway->event?->customData['contents'][0]['item_price'])->toBe(1000.0)
        ->and($gateway->event?->customData['contents'][0]['quantity'])->toBe(1);
});

test('queued jobs are unique and use bounded retry settings', function (): void {
    $event = new MetaConversionEvent('ViewContent', 'event-unique', now()->timestamp, 'https://prodeals.lk/', [], []);
    $eventJob = new SendMetaConversion($event);
    $purchaseJob = new SendMetaPurchase(42);

    expect($eventJob->uniqueId())->toBe('event-unique')
        ->and($eventJob)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($eventJob->event->toArray())->not->toHaveKey('referrer_url')
        ->and($purchaseJob->uniqueId())->toBe('Purchase:42')
        ->and($eventJob->tries)->toBe(5)
        ->and($eventJob->backoff)->toBe([10, 60, 300, 900])
        ->and($purchaseJob->tries)->toBe(5)
        ->and($purchaseJob->timeout)->toBe(15);
});

test('commerce still succeeds when a synchronous Meta delivery fails', function (): void {
    app()->instance(MetaConversionsGateway::class, new class implements MetaConversionsGateway
    {
        public function send(MetaConversionEvent $event, ?string $testEventCode = null): MetaConversionReceipt
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

test('storefront requests still succeed when the parameter builder fails', function (): void {
    app()->instance(MetaParameterBuilderService::class, new class(app(TrackingConsent::class)) extends MetaParameterBuilderService
    {
        /** @param array<string, mixed> $server */
        protected function processBuilder(ParamBuilder $builder, array $server): void
        {
            throw new RuntimeException('Simulated parameter builder failure');
        }
    });

    $this->get(route('home'))->assertOk();
});

test('synthetic command requires a temporary code and never displays credentials', function (): void {
    $gateway = new class implements MetaConversionsGateway
    {
        public ?MetaConversionEvent $event = null;

        public ?string $testEventCode = null;

        public function send(MetaConversionEvent $event, ?string $testEventCode = null): MetaConversionReceipt
        {
            $this->event = $event;
            $this->testEventCode = $testEventCode;

            return new MetaConversionReceipt(1, 'trace_test_123', []);
        }
    };
    app()->instance(MetaConversionsGateway::class, $gateway);

    $this->artisan('meta:conversions:test')->assertFailed();
    $this->artisan('meta:conversions:test', ['--test-event-code' => 'TEST123'])
        ->expectsOutputToContain('Meta accepted')
        ->doesntExpectOutput('test-access-token')
        ->assertSuccessful();

    expect($gateway->event?->name)->toBe('PageView')
        ->and($gateway->event?->customData)->toBe(['content_name' => 'Deployment verification'])
        ->and($gateway->event?->userData['em'][0])->toMatch('/^'.hash('sha256', 'meta-test@prodeals.lk').'\.[A-Za-z0-9_-]{8}$/')
        ->and(json_encode($gateway->event?->toArray(), JSON_THROW_ON_ERROR))->not->toContain('meta-test@prodeals.lk')
        ->and($gateway->testEventCode)->toBe('TEST123');
});

test('checkout test links are short lived signed and do not expose the Meta code', function (): void {
    $link = app(MetaTestSessionService::class)->createCheckoutLink('TEST123');

    expect($link)->toContain('/meta/conversions/test-checkout-session')
        ->and($link)->not->toContain('TEST123');

    $this->get($link)
        ->assertRedirect(route('home'))
        ->assertSessionHas('meta_conversions.test_event_code', 'TEST123');
    $this->get($link.'&changed=1')->assertForbidden();
    $invalidPayloadLink = URL::temporarySignedRoute(
        'meta.conversions.test_session',
        now()->addMinutes(15),
        ['payload' => 'invalid'],
    );
    $this->get($invalidPayloadLink)->assertNotFound();

    $this->artisan('meta:conversions:test-checkout-link')->assertFailed();
    $this->artisan('meta:conversions:test-checkout-link', ['--test-event-code' => 'invalid code'])
        ->assertFailed();
    $this->artisan('meta:conversions:test-checkout-link', ['--test-event-code' => 'TEST123'])
        ->expectsOutputToContain('/meta/conversions/test-checkout-session')
        ->doesntExpectOutput('TEST123')
        ->assertSuccessful();
});
