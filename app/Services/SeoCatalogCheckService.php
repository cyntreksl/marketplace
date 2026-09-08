<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\SeoMonitoringRepository;
use App\Exceptions\SeoMonitoringException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

class SeoCatalogCheckService
{
    public function __construct(
        private readonly MerchantFeedService $feed,
        private readonly SitemapService $sitemaps,
        private readonly ListingRepository $listings,
        private readonly SeoMonitoringRepository $state,
    ) {}

    /** @return array{fingerprint: string, count: int, captured_at: int} */
    public function snapshot(): array
    {
        $xml = $this->feed->generate();
        $offers = $this->offers($xml);

        return ['fingerprint' => hash('sha256', $xml), 'count' => count($offers), 'captured_at' => now()->getTimestamp()];
    }

    /** @return array{issues: list<string>, context: array<string, mixed>} */
    public function check(): array
    {
        $capturedAt = now()->timestamp;
        $expectedXml = $this->feed->generate();
        $expected = $this->offers($expectedXml);
        $actual = $this->offers($this->fetch(route('feeds.google_merchant')));
        $issues = [];
        $eligibleIds = [];

        foreach ($this->listings->merchantProducts() as $listing) {
            if ($listing->product_type === 'variant') {
                foreach ($listing->variants->where('is_active', true) as $variant) {
                    $eligibleIds[] = 'listing-'.$listing->id.'-variant-'.$variant->id;
                }
            } else {
                $eligibleIds[] = 'listing-'.$listing->id;
            }
        }

        $missing = array_values(array_diff($eligibleIds, array_keys($actual)));
        $unexpected = array_values(array_diff(array_keys($actual), $eligibleIds));
        if ($missing !== []) {
            $issues[] = 'Eligible offers missing from the public feed: '.implode(', ', $missing).'.';
        }
        if ($unexpected !== []) {
            $issues[] = 'Ineligible offers present in the public feed: '.implode(', ', $unexpected).'.';
        }

        foreach ($actual as $id => $offer) {
            if (isset($expected[$id]) && $expected[$id] !== $offer) {
                $issues[] = 'Public offer data differs from the catalog: '.$id.'.';
            }
        }

        $expectedIndex = $this->locations($this->sitemaps->index(), 'sitemapindex');
        $actualIndex = $this->locations($this->fetch(route('sitemap.index')), 'sitemapindex');
        if ($expectedIndex !== $actualIndex) {
            $issues[] = 'The public sitemap index differs from the catalog.';
        }

        $productUrls = [];
        foreach ($expectedIndex as $url) {
            $locations = $this->locations($this->fetch($url), 'urlset');
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (preg_match('~/sitemaps/products-([1-9][0-9]*)\\.xml$~', $path, $matches) === 1) {
                $expectedPage = $this->sitemaps->products((int) $matches[1]);
                if ($expectedPage === null || $locations !== $this->locations($expectedPage, 'urlset')) {
                    $issues[] = 'Public product sitemap differs from the catalog: page '.$matches[1].'.';
                }
                $productUrls = [...$productUrls, ...$locations];
            }
        }

        if (count($productUrls) !== count(array_unique($productUrls))) {
            $issues[] = 'Product URLs are duplicated across sitemap pages.';
        }

        $snapshot = ['fingerprint' => hash('sha256', $expectedXml), 'count' => count($expected), 'captured_at' => $capturedAt];
        if ($snapshot['fingerprint'] !== $this->snapshot()['fingerprint']) {
            throw new SeoMonitoringException('The catalog changed during validation. Run the catalog check again.');
        }

        if ($issues === []) {
            $this->state->put('catalog-baseline', $snapshot);
        }

        return ['issues' => $issues, 'context' => ['offer_count' => count($actual), 'sitemap_product_count' => count($productUrls), 'fingerprint' => $snapshot['fingerprint']]];
    }

    /** @return array<string, array<string, string>> */
    private function offers(string $xml): array
    {
        $root = $this->xml($xml, 'rss');
        if (! isset($root->channel)) {
            throw new SeoMonitoringException('Merchant XML has no channel.');
        }

        $offers = [];
        foreach ($root->channel->item as $item) {
            $fields = $item->children('http://base.google.com/ns/1.0');
            $id = (string) $fields->id;
            if (! preg_match('/^listing-[1-9][0-9]*(?:-variant-[1-9][0-9]*)?$/', $id) || isset($offers[$id])) {
                throw new SeoMonitoringException('Merchant XML contains an invalid or duplicate offer ID.');
            }

            foreach (['id', 'link', 'price', 'availability', 'image_link'] as $field) {
                if (count($fields->{$field}) !== 1 || trim((string) $fields->{$field}) === '') {
                    throw new SeoMonitoringException('Merchant XML has a missing or repeated required field for '.$id.'.');
                }
            }

            $offer = [];
            foreach (['link', 'price', 'availability', 'image_link'] as $field) {
                $offer[$field] = trim((string) $fields->{$field});
            }
            foreach (['link', 'image_link'] as $field) {
                if (! filter_var($offer[$field], FILTER_VALIDATE_URL) || ! in_array(parse_url($offer[$field], PHP_URL_SCHEME), ['https', 'http'], true)) {
                    throw new SeoMonitoringException('Merchant XML contains an invalid URL for '.$id.'.');
                }
            }
            if (! preg_match('/^[0-9]+\\.[0-9]{2} LKR$/', $offer['price']) || (float) $offer['price'] <= 0
                || ! in_array($offer['availability'], ['in_stock', 'out_of_stock', 'backorder', 'preorder'], true)) {
                throw new SeoMonitoringException('Merchant XML contains an invalid price, currency or availability for '.$id.'.');
            }

            $offers[$id] = $offer;
        }
        ksort($offers);

        return $offers;
    }

    /** @return list<string> */
    private function locations(string $xml, string $rootName): array
    {
        $root = $this->xml($xml, $rootName);
        if (($root->getDocNamespaces()[''] ?? null) !== 'http://www.sitemaps.org/schemas/sitemap/0.9') {
            throw new SeoMonitoringException('Sitemap XML has an invalid namespace.');
        }
        $root->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locations = array_map(fn (SimpleXMLElement $node): string => trim((string) $node), $root->xpath($rootName === 'sitemapindex' ? '/s:sitemapindex/s:sitemap/s:loc' : '/s:urlset/s:url/s:loc') ?: []);
        if (count($locations) !== count(array_unique($locations))) {
            throw new SeoMonitoringException('Sitemap XML contains duplicate locations.');
        }
        foreach ($locations as $url) {
            if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_HOST) !== parse_url(route('home'), PHP_URL_HOST)) {
                throw new SeoMonitoringException('Sitemap XML contains an invalid or foreign location.');
            }
        }
        sort($locations);

        return $locations;
    }

    private function xml(string $body, string $rootName): SimpleXMLElement
    {
        if (strlen($body) > 50000000 || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            throw new SeoMonitoringException('Discovery XML is oversized or contains a forbidden document type.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, options: LIBXML_NONET);
            if ($xml === false || $xml->getName() !== $rootName) {
                throw new SeoMonitoringException('Discovery endpoint returned malformed XML or an unexpected document.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function fetch(string $url): string
    {
        try {
            $response = Http::withUserAgent('ProDealsCatalogMonitor/1.0')->accept('application/xml')->connectTimeout(5)->timeout(20)
                ->withoutRedirecting()->retry([250, 1000, 2000], when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)))
                ->get($url);
            if ($response->status() !== 200) {
                throw new SeoMonitoringException('A public discovery endpoint did not return HTTP 200.');
            }

            return $response->body();
        } catch (Throwable) {
            throw new SeoMonitoringException('A public discovery endpoint could not be fetched successfully.');
        }
    }
}
