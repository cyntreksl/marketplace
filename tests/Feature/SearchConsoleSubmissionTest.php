<?php

use App\Contracts\GoogleSearchConsoleGateway;
use App\Contracts\GoogleSearchConsoleTokenProvider;
use App\Exceptions\SeoMonitoringException;
use App\Services\GoogleSearchConsoleApiService;
use App\Services\GoogleSearchConsoleTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config([
        'seo-monitoring.search_console.enabled' => true,
        'seo-monitoring.search_console.site_url' => 'sc-domain:prodeals.lk',
        'seo-monitoring.search_console.sitemap_url' => 'https://prodeals.lk/sitemap.xml',
    ]);
    Http::preventStrayRequests();
    Sleep::fake();
});

test('search console submits only the production root sitemap and refreshes an expired token', function () {
    $tokens = $this->mock(GoogleSearchConsoleTokenProvider::class);
    $tokens->shouldReceive('token')->once()->withNoArgs()->andReturn('old-token');
    $tokens->shouldReceive('token')->once()->with(true)->andReturn('new-token');
    Http::fake([
        'www.googleapis.com/webmasters/v3/*' => Http::sequence()->pushStatus(401)->pushStatus(204),
    ]);

    app(GoogleSearchConsoleApiService::class)->submitSitemap();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), rawurlencode('sc-domain:prodeals.lk'))
        && str_contains($request->url(), rawurlencode('https://prodeals.lk/sitemap.xml'))
        && $request->hasHeader('Authorization', 'Bearer new-token'));
});

test('search console retries transient responses and hides permanent provider details', function () {
    $tokens = $this->mock(GoogleSearchConsoleTokenProvider::class);
    $tokens->shouldReceive('token')->twice()->withNoArgs()->andReturn('token');
    Http::fake([
        'www.googleapis.com/webmasters/v3/*' => Http::sequence()
            ->push(['error' => 'PRIVATE_PROVIDER_DETAIL'], 500)
            ->pushStatus(429)
            ->pushStatus(204)
            ->push(['error' => 'PRIVATE_PROVIDER_DETAIL'], 403),
    ]);

    app(GoogleSearchConsoleApiService::class)->submitSitemap();
    Http::assertSentCount(3);

    expect(fn () => app(GoogleSearchConsoleApiService::class)->submitSitemap())
        ->toThrow(SeoMonitoringException::class, 'Verify property access');
});

test('search console authentication uses service account credentials and the webmasters scope', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    $path = tempnam(sys_get_temp_dir(), 'gsc-auth-');
    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'private_key' => $privateKey,
        'client_email' => 'search-console@example.iam.gserviceaccount.com',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config(['seo-monitoring.search_console.credentials_path' => $path]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-gsc-token', 'expires_in' => 3600, 'token_type' => 'Bearer'])]);

    try {
        $tokens = app(GoogleSearchConsoleTokenService::class);
        expect($tokens->token())->toBe('fake-gsc-token')
            ->and($tokens->token())->toBe('fake-gsc-token');
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) $request->body(), $body);
            $parts = explode('.', (string) ($body['assertion'] ?? ''));
            $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);

            return ($payload['scope'] ?? null) === 'https://www.googleapis.com/auth/webmasters';
        });
    } finally {
        unlink($path);
    }
});

test('submission command is idempotent and provider failures remain safe', function () {
    $gateway = $this->mock(GoogleSearchConsoleGateway::class);
    $gateway->shouldReceive('submitSitemap')->twice()->andReturnNull();

    $this->artisan('seo:submit-search-console-sitemap')->assertSuccessful();
    $this->artisan('seo:submit-search-console-sitemap')->assertSuccessful();

    $this->mock(GoogleSearchConsoleGateway::class)
        ->shouldReceive('submitSitemap')
        ->once()
        ->andThrow(new SeoMonitoringException('Search Console is unavailable after three retries.'));

    $this->artisan('seo:submit-search-console-sitemap')
        ->expectsOutputToContain('Search Console is unavailable')
        ->assertFailed();
});

test('invalid targets and missing credentials fail without exposing secrets', function () {
    config(['seo-monitoring.search_console.site_url' => 'https://wrong.example']);
    $this->mock(GoogleSearchConsoleTokenProvider::class)->shouldReceive('token')->never();

    expect(fn () => app(GoogleSearchConsoleApiService::class)->submitSitemap())
        ->toThrow(SeoMonitoringException::class, 'must match the production domain');

    config(['seo-monitoring.search_console.credentials_path' => '/missing/private-secret.json']);
    expect(fn () => app(GoogleSearchConsoleTokenService::class)->token())
        ->toThrow(SeoMonitoringException::class, 'missing or unreadable');
});
