<?php

use App\Models\Listing;
use App\Services\IndexNowService;
use App\Services\SitemapService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['indexnow.enabled' => true, 'seo-monitoring.cache_store' => 'array']);
    Http::preventStrayRequests();
});

test('verification route serves the exact UTF-8 key without a session', function () {
    $this->get(route('indexnow.key'))->assertOk()
        ->assertContent(IndexNowService::KEY)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertCookieMissing(config('session.cookie'));
});

test('first run establishes a baseline and unchanged URLs are not submitted', function () {
    Listing::factory()->create();
    $this->artisan('seo:submit-indexnow')->assertSuccessful();
    $this->artisan('seo:submit-indexnow')->assertSuccessful();
    Http::assertNothingSent();
});

test('scheduler submits added updated and removed public URLs', function () {
    $listing = Listing::factory()->create();
    $private = Listing::factory()->create(['status' => 'draft']);
    $service = app(IndexNowService::class);
    expect($service->submitChanges())->toBe(0);
    $oldUrl = route('listings.show', $listing->slug);
    $this->travel(1)->minutes();
    $listing->update(['slug' => 'renamed-product']);
    $newUrl = route('listings.show', $listing->slug);
    Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);

    expect($service->submitChanges())->toBeGreaterThanOrEqual(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['host'] === parse_url(route('indexnow.key'), PHP_URL_HOST)
        && $request['key'] === IndexNowService::KEY
        && $request['keyLocation'] === route('indexnow.key')
        && in_array($oldUrl, $request['urlList'], true)
        && in_array($newUrl, $request['urlList'], true)
        && ! in_array(route('listings.show', $private->slug), $request['urlList'], true));

    $this->travel(1)->minutes();
    $listing->update(['title' => 'Updated product']);
    expect($service->submitChanges())->toBeGreaterThanOrEqual(1);
    $listing->update(['status' => 'archived']);
    expect($service->submitChanges())->toBeGreaterThanOrEqual(1);
    Http::assertSent(fn (Request $request): bool => in_array($newUrl, $request['urlList'], true));
    expect($service->submitChanges())->toBe(0);
});

test('failed submissions retain changes for the next scheduled run', function () {
    $service = app(IndexNowService::class);
    $service->submitChanges();
    $listing = Listing::factory()->create();
    Http::fake(['api.indexnow.org/*' => Http::sequence()->push('', 429)->push('', 200)]);
    $this->artisan('seo:submit-indexnow')->assertFailed();
    $this->artisan('seo:submit-indexnow')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => in_array(route('listings.show', $listing->slug), $request['urlList'], true));
    expect($service->submitChanges())->toBe(0);
});

test('connection failures can be retried and disabled integration sends nothing', function () {
    $service = app(IndexNowService::class);
    $service->submitChanges();
    Listing::factory()->create();
    Http::fake(['api.indexnow.org/*' => Http::failedConnection()]);
    $this->artisan('seo:submit-indexnow')->assertFailed();
    config(['indexnow.enabled' => false]);
    Http::fake();
    $this->artisan('seo:submit-indexnow')->assertSuccessful();
    Http::assertNothingSent();
});

test('submissions respect the 10000 URL batch limit', function () {
    $sitemaps = $this->mock(SitemapService::class);
    $empty = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />';
    $entries = '';
    for ($index = 0; $index < 10001; $index++) {
        $entries .= '<url><loc>'.rtrim(route('home'), '/').'/products/'.$index.'</loc></url>';
    }
    $full = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</urlset>';
    $sitemaps->shouldReceive('staticPages')->andReturn($empty, $full);
    foreach (['categories', 'brands', 'stores', 'guides'] as $method) {
        $sitemaps->shouldReceive($method)->andReturn($empty);
    }
    $sitemaps->shouldReceive('products')->with(1)->andReturn(null);
    $service = app(IndexNowService::class);
    expect($service->submitChanges())->toBe(0);
    Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
    expect($service->submitChanges())->toBe(10001);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => count($request['urlList']) === 10000);
    Http::assertSent(fn (Request $request): bool => count($request['urlList']) === 1);
});

test('foreign hosts are rejected before submission', function () {
    $sitemaps = $this->mock(SitemapService::class);
    $empty = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" />';
    $sitemaps->shouldReceive('staticPages')->andReturn($empty,
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://other.example/product</loc></url></urlset>');
    foreach (['categories', 'brands', 'stores', 'guides'] as $method) {
        $sitemaps->shouldReceive($method)->andReturn($empty);
    }
    $sitemaps->shouldReceive('products')->with(1)->andReturn(null);
    $service = app(IndexNowService::class);
    $service->submitChanges();
    expect(fn () => $service->submitChanges())->toThrow(LogicException::class);
    Http::assertNothingSent();
});

test('IndexNow runs every fifteen minutes in production with overlap protection', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'seo:submit-indexnow'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *')
        ->and($event->environments)->toBe(['production'])
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->runInBackground)->toBeTrue();
});
