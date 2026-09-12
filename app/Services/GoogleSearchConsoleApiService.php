<?php

namespace App\Services;

use App\Contracts\GoogleSearchConsoleGateway;
use App\Contracts\GoogleSearchConsoleTokenProvider;
use App\Exceptions\SeoMonitoringException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class GoogleSearchConsoleApiService implements GoogleSearchConsoleGateway
{
    public function __construct(private readonly GoogleSearchConsoleTokenProvider $tokens) {}

    public function submitSitemap(): void
    {
        $siteUrl = (string) config('seo-monitoring.search_console.site_url');
        $sitemapUrl = (string) config('seo-monitoring.search_console.sitemap_url');

        if ($siteUrl !== 'sc-domain:prodeals.lk' || $sitemapUrl !== 'https://prodeals.lk/sitemap.xml') {
            throw new SeoMonitoringException('Search Console property and sitemap URLs must match the production domain.');
        }

        $url = 'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteUrl).'/sitemaps/'.rawurlencode($sitemapUrl);
        $token = $this->tokens->token();
        $refreshed = false;
        $retries = 0;

        while (true) {
            try {
                $response = Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(20)
                    ->withoutRedirecting()->put($url);

                if ($response->status() === 401 && ! $refreshed) {
                    $refreshed = true;
                    $token = $this->tokens->token(true);

                    continue;
                }

                if ($response->successful()) {
                    return;
                }

                if (! $response->serverError() && $response->status() !== 429) {
                    throw new SeoMonitoringException('Search Console sitemap submission failed (HTTP '.$response->status().'). Verify property access.');
                }
            } catch (ConnectionException) {
                // Network failures use the same bounded retry budget as transient responses.
            }

            if ($retries >= 3) {
                throw new SeoMonitoringException('Search Console is unavailable after three retries.');
            }

            Sleep::for([250, 1000, 2000][$retries++])->milliseconds();
        }
    }
}
