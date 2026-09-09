<?php

use App\Services\MetaConversionsApiService;
use App\Support\MetaConversionEvent;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'services.meta_conversions.pixel_id' => '2154698912092970',
        'services.meta_conversions.access_token' => 'test-access-token',
        'services.meta_conversions.api_version' => 'v25.0',
    ]);
    Http::preventStrayRequests();
});

function metaTestEvent(): MetaConversionEvent
{
    return new MetaConversionEvent(
        name: 'ViewContent',
        id: 'event-123',
        occurredAt: 1788775200,
        sourceUrl: 'https://prodeals.lk/listings/camera',
        userData: ['em' => [hash('sha256', 'buyer@example.com')]],
        customData: ['currency' => 'LKR', 'value' => 1250.0, 'content_ids' => ['42']],
        referrerUrl: 'https://www.facebook.com/ad.AQEAAQMB',
    );
}

test('gateway sends the expected v25 payload with bearer authentication and timeouts', function (): void {
    $options = [];
    Http::fake(['graph.facebook.com/*' => function (Request $request, array $requestOptions) use (&$options) {
        $options = $requestOptions;

        return Http::response(['events_received' => 1]);
    }]);

    (new MetaConversionsApiService)->send(metaTestEvent(), 'TEST123');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://graph.facebook.com/v25.0/2154698912092970/events'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $body['test_event_code'] === 'TEST123'
            && $body['data'][0]['event_name'] === 'ViewContent'
            && $body['data'][0]['event_id'] === 'event-123'
            && $body['data'][0]['action_source'] === 'website'
            && $body['data'][0]['referrer_url'] === 'https://www.facebook.com/ad.AQEAAQMB'
            && $body['data'][0]['custom_data']['currency'] === 'LKR'
            && $body['data'][0]['custom_data']['value'] === 1250.0
            && ! str_contains($request->body(), 'buyer@example.com');
    });
    expect($options['connect_timeout'])->toBe(3)
        ->and($options['timeout'])->toBe(10);
});

test('gateway retries transient responses but not permanent Meta errors', function (): void {
    Http::fakeSequence('graph.facebook.com/*')
        ->pushStatus(500)
        ->pushStatus(429)
        ->push(['events_received' => 1]);

    (new MetaConversionsApiService)->send(metaTestEvent());
    Http::assertSentCount(3);

    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400)]);

    expect(fn () => (new MetaConversionsApiService)->send(metaTestEvent()))
        ->toThrow(RequestException::class);
    Http::assertSentCount(1);
});

test('gateway retries connection failures', function (): void {
    Http::fakeSequence('graph.facebook.com/*')
        ->pushFailedConnection()
        ->push(['events_received' => 1]);

    (new MetaConversionsApiService)->send(metaTestEvent());

    Http::assertSentCount(2);
});
