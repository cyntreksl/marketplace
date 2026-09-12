<?php

use App\Contracts\GoogleMerchantTokenProvider;
use App\Contracts\Repositories\SeoMonitoringRepository;
use App\Exceptions\SeoMonitoringException;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Models\SellerProfile;
use App\Notifications\SeoMonitoringNotification;
use App\Services\GoogleMerchantApiService;
use App\Services\GoogleMerchantTokenService;
use App\Services\MerchantFeedService;
use App\Services\SeoCatalogCheckService;
use App\Services\SeoMonitoringService;
use App\Services\SitemapService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['seo-monitoring.cache_store' => 'array', 'seo-monitoring.alert_email' => 'prodealslk@gmail.com']);
    $this->travelTo(CarbonImmutable::parse('2026-09-09T03:30:00+05:30'));
    Notification::fake();
    Sleep::fake();
    Http::preventStrayRequests();
});

/** @return array<string, string> */
function seoPublicDocuments(): array
{
    $sitemaps = app(SitemapService::class);
    $documents = [
        route('feeds.google_merchant') => app(MerchantFeedService::class)->generate(),
        route('sitemap.index') => $sitemaps->index(),
        route('sitemap.static') => $sitemaps->staticPages(),
        route('sitemap.stores') => $sitemaps->stores(),
        route('sitemap.categories') => $sitemaps->categories(),
        route('sitemap.brands') => $sitemaps->brands(),
        route('sitemap.guides') => $sitemaps->guides(),
    ];
    for ($page = 1; ($xml = $sitemaps->products($page)) !== null; $page++) {
        $documents[route('sitemap.products', $page)] = $xml;
    }

    return $documents;
}

/** @param array<string, string> $documents */
function fakeSeoDocuments(array $documents): void
{
    Http::fake(array_map(fn (string $xml) => Http::response($xml, 200, ['Content-Type' => 'application/xml']), $documents));
}

/** @return array<string, mixed> */
function seoMerchantSource(): array
{
    return [
        'input' => 'FILE',
        'primaryProductDataSource' => ['contentLanguage' => 'en', 'feedLabel' => 'LK', 'countries' => ['LK']],
        'fileInput' => ['fileInputType' => 'FETCH', 'fetchSettings' => [
            'enabled' => true, 'frequency' => 'FREQUENCY_DAILY', 'timeZone' => 'Asia/Colombo',
            'timeOfDay' => ['hours' => 2], 'fetchUri' => route('feeds.google_merchant'),
        ]],
    ];
}

/** @param array<string, mixed> $upload
 * @param  array<string, mixed>|null  $source
 */
function fakeSeoMerchant(array $upload = [], ?array $source = null): void
{
    test()->mock(GoogleMerchantTokenProvider::class)->shouldReceive('token')->andReturn('test-access-token');
    Http::fake([
        'merchantapi.googleapis.com/datasources/v1/accounts/*/dataSources/*/fileUploads/latest' => Http::response([
            'processingState' => 'SUCCEEDED', 'uploadTime' => now()->subHour()->toIso8601String(), 'itemsTotal' => '1',
            ...$upload,
        ]),
        'merchantapi.googleapis.com/datasources/v1/accounts/*/dataSources/*' => Http::response($source ?? seoMerchantSource()),
    ]);
}

function seoBaseline(): void
{
    app(SeoMonitoringRepository::class)->put('catalog-baseline', [
        ...app(SeoCatalogCheckService::class)->snapshot(),
        'captured_at' => now()->subHours(2)->getTimestamp(),
    ]);
}

test('catalog command reconciles eligible variants and sold out offers separately from sitemap URLs', function () {
    config(['marketplace.seo.sitemap_product_chunk_size' => 2]);
    Listing::factory()->count(12)->has(ListingMedia::factory(), 'media')->create();
    Listing::factory()->has(ListingMedia::factory(), 'media')->create(['stock_quantity' => 0, 'reserved_quantity' => 0]);
    $product = Listing::factory()->has(ListingMedia::factory(), 'media')->create(['product_type' => 'variant']);
    ListingVariant::factory()->count(2)->sequence(['position' => 0], ['position' => 1])->for($product)->create();
    ListingVariant::factory()->for($product)->create(['is_active' => false, 'position' => 2]);
    foreach (['draft', 'pending_review', 'changes_requested', 'rejected', 'archived'] as $status) {
        Listing::factory()->has(ListingMedia::factory(), 'media')->create(['status' => $status]);
    }
    Listing::factory()->has(ListingMedia::factory(), 'media')->for(SellerProfile::factory()->state(['status' => 'pending_review']))->create();
    Listing::factory()->has(ListingMedia::factory(), 'media')->for(Category::factory()->state(['is_active' => false]))->create();
    fakeSeoDocuments(seoPublicDocuments());
    $this->artisan('seo:check-catalog')->assertSuccessful();
    $state = app(SeoMonitoringRepository::class);
    expect($state->get('catalog-baseline')['count'])->toBe(15)
        ->and($state->get('alert:catalog')['result']['sitemap_product_count'])->toBe(14);
    Notification::assertNothingSent();
});

test('new approvals and archives need no static generation', function () {
    $listing = Listing::factory()->has(ListingMedia::factory(), 'media')->create(['status' => 'pending_review']);
    $first = app(SeoCatalogCheckService::class)->snapshot();
    $listing->update(['status' => 'approved']);
    $approved = app(SeoCatalogCheckService::class)->snapshot();
    fakeSeoDocuments(seoPublicDocuments());
    $this->artisan('seo:check-catalog')->assertSuccessful();
    $listing->update(['status' => 'archived']);
    expect($first['count'])->toBe(0)->and($approved['count'])->toBe(1)
        ->and($approved['fingerprint'])->not->toBe($first['fingerprint'])
        ->and(app(SeoCatalogCheckService::class)->snapshot()['count'])->toBe(0);
});

test('catalog check rejects missing duplicate and incorrect discovery data', function (string $case) {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create(['price' => 100]);
    $documents = seoPublicDocuments();
    $url = route('feeds.google_merchant');
    $xml = $documents[$url];
    $documents[$url] = match ($case) {
        'missing' => preg_replace('/<item>.*?<\/item>/s', '', $xml),
        'duplicate' => preg_replace('/(<item>.*?<\/item>)/s', '$1$1', $xml),
        'currency' => str_replace('100.00 LKR', '100.00 USD', $xml),
        'price' => str_replace('100.00 LKR', '200.00 LKR', $xml),
        'availability' => str_replace('<g:availability>in_stock</g:availability>', '<g:availability>out_of_stock</g:availability>', $xml),
        'image' => preg_replace('/<g:image_link>.*?<\/g:image_link>/', '', $xml),
        'url' => preg_replace('/<g:link>.*?<\/g:link>/', '<g:link>javascript:alert(1)</g:link>', $xml),
        'malformed' => '<html>Unavailable</html>',
        'entity' => '<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]>'.$xml,
    };
    fakeSeoDocuments($documents);
    $this->artisan('seo:check-catalog')->assertFailed();
    expect(app(SeoMonitoringRepository::class)->get('catalog-baseline'))->toBeNull();
    Notification::assertSentOnDemand(SeoMonitoringNotification::class);
})->with(['missing', 'duplicate', 'currency', 'price', 'availability', 'image', 'url', 'malformed', 'entity']);

test('catalog check catches sitemap omissions and unreachable feeds', function () {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    $documents = seoPublicDocuments();
    $documents[route('sitemap.products', 1)] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    fakeSeoDocuments($documents);
    $this->artisan('seo:check-catalog')->assertFailed();
    Http::fake([route('feeds.google_merchant') => Http::response('secret-provider-body', 503)]);
    $this->artisan('seo:check-catalog')->assertFailed();
    expect(json_encode(app(SeoMonitoringRepository::class)->get('alert:catalog')))->not->toContain('secret-provider-body');
});

test('merchant command reconciles successful stable imports without email', function () {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    seoBaseline();
    fakeSeoMerchant();
    $this->artisan('seo:check-merchant')->assertSuccessful();
    expect(app(SeoMonitoringRepository::class)->get('alert:merchant')['result']['reconciliation_pending'])->toBeFalse();
    Notification::assertNothingSent();
    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer test-access-token'));
});

test('merchant command reports failed stale misconfigured and mismatched imports', function (string $case) {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    seoBaseline();
    $source = seoMerchantSource();
    $upload = [];
    match ($case) {
        'failed' => $upload = ['processingState' => 'FAILED'],
        'stale' => $upload = ['uploadTime' => now()->subHours(31)->toIso8601String()],
        'count' => $upload = ['itemsTotal' => '0'],
        'errors' => $upload = ['issues' => [['code' => 'invalid_price', 'severity' => 'ERROR', 'count' => '1']]],
        'disabled' => $source['fileInput']['fetchSettings']['enabled'] = false,
        'schedule' => $source['fileInput']['fetchSettings']['timeOfDay']['hours'] = 8,
        'minutes' => $source['fileInput']['fetchSettings']['timeOfDay']['minutes'] = 30,
        'seconds' => $source['fileInput']['fetchSettings']['timeOfDay']['seconds'] = 30,
        'targeting' => $source['primaryProductDataSource']['countries'] = ['US'],
        'feed_url' => $source['fileInput']['fetchSettings']['fetchUri'] = 'https://example.com/wrong.xml',
        'invalid_state' => $upload = ['processingState' => 'UNKNOWN'],
        'invalid_date' => $upload = ['uploadTime' => 'not-a-date'],
    };
    fakeSeoMerchant($upload, $source);
    $this->artisan('seo:check-merchant')->assertFailed();
    Notification::assertSentOnDemand(SeoMonitoringNotification::class);
})->with(['failed', 'stale', 'count', 'errors', 'disabled', 'schedule', 'minutes', 'seconds', 'targeting', 'feed_url', 'invalid_state', 'invalid_date']);

test('changed catalog defers reconciliation without a false recovery', function () {
    $listing = Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    seoBaseline();
    fakeSeoMerchant(['itemsTotal' => '0']);
    $this->artisan('seo:check-merchant')->assertFailed();
    $listing->update(['price' => 9876]);
    fakeSeoMerchant(['itemsTotal' => '0']);
    $this->artisan('seo:check-merchant')->assertSuccessful();
    Notification::assertSentOnDemandTimes(SeoMonitoringNotification::class, 1);
    expect(app(SeoMonitoringRepository::class)->get('alert:merchant')['failed'])->toBeTrue();
});

test('processing imports retain last success for the thirty hour deadline', function () {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    seoBaseline();
    fakeSeoMerchant();
    $this->artisan('seo:check-merchant')->assertSuccessful();
    fakeSeoMerchant(['processingState' => 'IN_PROGRESS']);
    $this->artisan('seo:check-merchant')->assertSuccessful();
    $this->travel(31)->hours();
    $this->artisan('seo:check-merchant')->assertFailed();
});

test('missing baseline is actionable and warning-only imports remain healthy', function () {
    Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    fakeSeoMerchant();
    $this->artisan('seo:check-merchant')->assertFailed();
    seoBaseline();
    fakeSeoMerchant(['issues' => [['code' => 'optional_attribute', 'severity' => 'WARNING']]]);
    $this->artisan('seo:check-merchant')->assertSuccessful();
});

test('monitor sends changed failures weekly reminders and one recovery', function () {
    $monitor = app(SeoMonitoringService::class);
    $failure = fn () => ['issues' => ['Missing listing-1.'], 'context' => []];
    expect($monitor->run('catalog', $failure))->toBeFalse();
    $monitor->run('catalog', $failure);
    Notification::assertSentOnDemandTimes(SeoMonitoringNotification::class, 1);
    $this->travel(7)->days();
    $monitor->run('catalog', $failure);
    $monitor->run('catalog', fn () => ['issues' => ['Missing listing-2.'], 'context' => []]);
    $monitor->run('catalog', fn () => ['issues' => [], 'context' => []]);
    $monitor->run('catalog', fn () => ['issues' => [], 'context' => []]);
    Notification::assertSentOnDemandTimes(SeoMonitoringNotification::class, 4);
    Notification::assertSentOnDemand(SeoMonitoringNotification::class, fn ($notification, $channels, $notifiable) => $notification->recovered && $notifiable->routes['mail'] === 'prodealslk@gmail.com');
});

test('raw exceptions and credentials never enter monitoring records', function () {
    expect(app(SeoMonitoringService::class)->run('catalog', fn () => throw new RuntimeException('private_key=SECRET_ACCESS_TOKEN')))->toBeFalse();
    expect(json_encode(app(SeoMonitoringRepository::class)->get('alert:catalog')))->not->toContain('SECRET_ACCESS_TOKEN');
    Notification::assertSentOnDemand(SeoMonitoringNotification::class, fn ($notification) => ! str_contains(implode(' ', $notification->issues), 'SECRET_ACCESS_TOKEN'));
});

test('API retries transient failures and refreshes unauthorized tokens once', function () {
    $tokens = $this->mock(GoogleMerchantTokenProvider::class);
    $tokens->shouldReceive('token')->once()->withNoArgs()->andReturn('old-token');
    $tokens->shouldReceive('token')->once()->with(true)->andReturn('new-token');
    Http::fake(['merchantapi.googleapis.com/*' => Http::sequence()->pushStatus(500)->pushStatus(429)->pushStatus(401)->push(seoMerchantSource())]);
    expect(app(GoogleMerchantApiService::class)->source()['input'])->toBe('FILE');
    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer new-token'));
});

test('API stops after bounded retries and persistent authorization failures', function (int $status, int $requests) {
    $this->mock(GoogleMerchantTokenProvider::class)->shouldReceive('token')->andReturn('sensitive-token');
    Http::fake(['merchantapi.googleapis.com/*' => Http::response('sensitive-provider-body', $status)]);
    expect(fn () => app(GoogleMerchantApiService::class)->source())->toThrow(SeoMonitoringException::class);
    Http::assertSentCount($requests);
})->with([[503, 4], [401, 2], [403, 1]]);

test('Google authentication uses fake HTTP and caches tokens only in memory', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    $path = tempnam(sys_get_temp_dir(), 'seo-auth-');
    file_put_contents($path, json_encode([
        'type' => 'service_account', 'private_key' => $privateKey,
        'client_email' => 'monitor@example.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config(['seo-monitoring.merchant.credentials_path' => $path]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600, 'token_type' => 'Bearer'])]);
    try {
        $tokens = app(GoogleMerchantTokenService::class);
        expect($tokens->token())->toBe('fake-token')->and($tokens->token())->toBe('fake-token');
        Http::assertSentCount(1);
        expect($tokens->token(true))->toBe('fake-token');
        Http::assertSentCount(2);
    } finally {
        unlink($path);
    }
});

test('missing credentials fail safely', function () {
    config(['seo-monitoring.merchant.credentials_path' => '/missing/seo-credentials.json']);
    expect(fn () => app(GoogleMerchantTokenService::class)->token())->toThrow(SeoMonitoringException::class, 'missing or unreadable');
});

test('schedules use Colombo time and prevent duplicate server execution', function () {
    $events = collect(app(Schedule::class)->events());
    foreach (['seo:check-catalog' => '45 1 * * *', 'seo:check-merchant' => '30 3 * * *'] as $command => $expression) {
        $event = $events->first(fn ($event) => str_contains($event->command ?? '', $command));
        expect($event)->not->toBeNull()->and($event->expression)->toBe($expression)
            ->and($event->timezone)->toBe('Asia/Colombo')->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue()->and($event->environments)->toBe(['production']);
    }
});

test('monitoring messages render through shared mail components', function () {
    $mail = (new SeoMonitoringNotification('merchant', ['Google import failed.']))->toMail(new stdClass);
    expect($mail->subject)->toBe('ProDeals SEO: merchant needs attention')
        ->and($mail->render()->toHtml())->toContain('Google import failed.', 'ProDeals');
});

test('catalog validation detects changes occurring during public reads', function () {
    $listing = Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    $documents = seoPublicDocuments();
    Http::fake(function ($request) use ($listing, $documents) {
        if ($request->url() === route('feeds.google_merchant')) {
            $listing->update(['price' => 98765]);
        }

        return Http::response($documents[$request->url()]);
    });
    expect(fn () => app(SeoCatalogCheckService::class)->check())->toThrow(SeoMonitoringException::class, 'changed during validation');
    expect(app(SeoMonitoringRepository::class)->get('catalog-baseline'))->toBeNull();
});

test('catalog validation detects unexpected offers and missing image data', function () {
    $listing = Listing::factory()->has(ListingMedia::factory(), 'media')->create();
    $documents = seoPublicDocuments();
    $listing->update(['status' => 'draft']);
    fakeSeoDocuments($documents);
    $this->artisan('seo:check-catalog')->assertFailed();
    Notification::assertSentOnDemand(SeoMonitoringNotification::class, fn ($notification) => str_contains(implode(' ', $notification->issues), 'Ineligible offers'));
});

test('eligible products without primary images fail coverage checks', function () {
    Listing::factory()->create();
    fakeSeoDocuments(seoPublicDocuments());
    $this->artisan('seo:check-catalog')->assertFailed();
});

test('foreign or incorrectly namespaced sitemaps are rejected', function (string $xml) {
    $documents = seoPublicDocuments();
    $documents[route('sitemap.index')] = $xml;
    fakeSeoDocuments($documents);
    $this->artisan('seo:check-catalog')->assertFailed();
})->with([
    '<sitemapindex xmlns="wrong"/>',
    '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://other.example/sitemap.xml</loc></sitemap></sitemapindex>',
]);

test('network failures are retried and exhausted with a sanitized message', function () {
    $this->mock(GoogleMerchantTokenProvider::class)->shouldReceive('token')->andReturn('private-token');
    Http::fake(['merchantapi.googleapis.com/*' => Http::failedConnection('sensitive-network-message')]);
    expect(fn () => app(GoogleMerchantApiService::class)->latestUpload())->toThrow(SeoMonitoringException::class, 'after three retries');
});

test('invalid API configuration and malformed responses fail safely', function () {
    config(['seo-monitoring.merchant.account_id' => '../invalid']);
    expect(fn () => app(GoogleMerchantApiService::class)->source())->toThrow(SeoMonitoringException::class, 'IDs must be configured');
    config(['seo-monitoring.merchant.account_id' => '5849184229']);
    $this->mock(GoogleMerchantTokenProvider::class)->shouldReceive('token')->andReturn('test-token');
    Http::fake(['merchantapi.googleapis.com/*' => Http::response('not-json', 200)]);
    expect(fn () => app(GoogleMerchantApiService::class)->source())->toThrow(SeoMonitoringException::class, 'invalid response');
});

test('invalid credential files never expose their content', function () {
    $path = tempnam(sys_get_temp_dir(), 'seo-invalid-auth-');
    file_put_contents($path, '{"type":"not-a-service-account","private_key":"SECRET"}');
    config(['seo-monitoring.merchant.credentials_path' => $path]);
    try {
        expect(fn () => app(GoogleMerchantTokenService::class)->token())->toThrow(SeoMonitoringException::class, 'authentication failed');
    } finally {
        unlink($path);
    }
});

test('monitoring refuses duplicate locks without executing work', function () {
    $state = app(SeoMonitoringRepository::class);
    $executed = false;
    $state->synchronized('catalog', function () use (&$executed) {
        expect(app(SeoMonitoringService::class)->run('catalog', function () use (&$executed) {
            $executed = true;

            return ['issues' => [], 'context' => []];
        }))->toBeFalse();
    });
    expect($executed)->toBeFalse();
});

test('notification enqueue failure remains retryable', function () {
    $this->mock(Dispatcher::class)
        ->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP_SECRET'));
    expect(app(SeoMonitoringService::class)->run('merchant', fn () => ['issues' => ['Import failed.'], 'context' => []]))->toBeFalse();
    expect(app(SeoMonitoringRepository::class)->get('alert:merchant'))->toBeNull();
});

test('setup delivery test is explicit and does not create a failure record', function () {
    fakeSeoDocuments(seoPublicDocuments());
    $this->artisan('seo:check-catalog --test-notification')->assertSuccessful();
    Notification::assertSentOnDemand(SeoMonitoringNotification::class, fn ($notification) => $notification->test
        && str_contains($notification->toMail(new stdClass)->subject, 'delivery test'));
    expect(app(SeoMonitoringRepository::class)->get('alert:catalog')['failed'])->toBeFalse();
});

test('monitoring retains structured success logs independently of production warning level', function () {
    Storage::fake('local');
    config([
        'logging.channels.single.level' => 'warning',
        'logging.channels.seo-monitoring.path' => Storage::disk('local')->path('seo-monitoring.log'),
    ]);
    Log::forgetChannel('seo-monitoring');
    $monitor = app(SeoMonitoringService::class);
    expect($monitor->run('catalog', fn () => ['issues' => [], 'context' => ['offer_count' => 51]]))->toBeTrue();
    expect($monitor->run('merchant', fn () => throw new RuntimeException('SECRET_PRIVATE_KEY')))->toBeFalse();
    $files = Storage::disk('local')->allFiles();
    expect($files)->toHaveCount(1);
    $contents = Storage::disk('local')->get($files[0]);
    $records = array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), explode("\n", trim($contents)));
    expect($records)->toHaveCount(2)
        ->and($records[0]['level_name'])->toBe('INFO')
        ->and($records[0]['context']['check'])->toBe('catalog')
        ->and($records[0]['context']['offer_count'])->toBe(51)
        ->and($records[1]['level_name'])->toBe('ERROR')
        ->and($contents)->not->toContain('SECRET_PRIVATE_KEY');
    Log::forgetChannel('seo-monitoring');
});
