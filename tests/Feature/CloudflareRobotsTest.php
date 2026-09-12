<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('cloudflare managed robots is disabled when deployment credentials are configured', function () {
    config([
        'seo-monitoring.cloudflare.zone_id' => 'test-zone',
        'seo-monitoring.cloudflare.api_token' => 'private-token',
    ]);
    Http::preventStrayRequests();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

    $this->artisan('seo:disable-cloudflare-managed-robots')
        ->expectsOutputToContain('is disabled')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.cloudflare.com/client/v4/zones/test-zone/bot_management'
        && $request->hasHeader('Authorization', 'Bearer private-token')
        && $request['is_robots_txt_managed'] === false);
});

test('cloudflare provider errors remain safe and missing credentials are a no-op', function () {
    Http::preventStrayRequests();
    config(['seo-monitoring.cloudflare.zone_id' => null, 'seo-monitoring.cloudflare.api_token' => null]);
    $this->artisan('seo:disable-cloudflare-managed-robots')->assertSuccessful();
    Http::assertNothingSent();

    config(['seo-monitoring.cloudflare.zone_id' => 'test-zone', 'seo-monitoring.cloudflare.api_token' => 'SECRET_TOKEN']);
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'SECRET_PROVIDER_BODY']]], 403)]);

    $this->artisan('seo:disable-cloudflare-managed-robots')
        ->doesntExpectOutputToContain('SECRET_TOKEN')
        ->doesntExpectOutputToContain('SECRET_PROVIDER_BODY')
        ->assertFailed();
});
