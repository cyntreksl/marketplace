<?php

namespace App\Services;

use App\Contracts\IndexNowGateway;
use App\Contracts\Repositories\SeoMonitoringRepository;
use LogicException;

class IndexNowService
{
    public const KEY = '7832bff8224749298729a9e886772b23';

    public function __construct(
        private readonly SitemapService $sitemaps,
        private readonly SeoMonitoringRepository $state,
        private readonly IndexNowGateway $gateway,
    ) {}

    public function submitChanges(): int
    {
        if (! config('indexnow.enabled')) {
            return 0;
        }

        $keyLocation = route('indexnow.key');
        $stateKey = 'indexnow:'.hash('sha256', $keyLocation);

        return (int) $this->state->synchronized($stateKey, function () use ($keyLocation, $stateKey): int {
            $current = $this->snapshot();
            $previous = $this->state->get($stateKey);

            if ($previous === null) {
                $this->state->put($stateKey, $current);

                return 0;
            }

            $changed = array_keys(array_diff_assoc($current, $previous));
            $removed = array_keys(array_diff_key($previous, $current));
            $urls = array_values(array_unique([...$changed, ...$removed]));

            foreach ($urls as $url) {
                if (parse_url($url, PHP_URL_HOST) !== parse_url($keyLocation, PHP_URL_HOST)) {
                    throw new LogicException('IndexNow URLs must belong to the verification key host.');
                }
            }

            foreach (array_chunk($urls, 10000) as $batch) {
                $this->gateway->submit($keyLocation, $batch);
            }

            $this->state->put($stateKey, $current);

            return count($urls);
        });
    }

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $snapshot = [];
        $documents = [
            $this->sitemaps->staticPages(), $this->sitemaps->categories(),
            $this->sitemaps->brands(), $this->sitemaps->stores(), $this->sitemaps->guides(),
        ];

        foreach ($documents as $xml) {
            $snapshot += $this->fingerprints($xml);
        }

        for ($page = 1; ($xml = $this->sitemaps->products($page)) !== null; $page++) {
            $snapshot += $this->fingerprints($xml);
        }

        return $snapshot;
    }

    /** @return array<string, string> */
    private function fingerprints(string $xml): array
    {
        $document = simplexml_load_string($xml, options: LIBXML_NONET);
        if ($document === false) {
            throw new LogicException('Unable to read the IndexNow sitemap snapshot.');
        }

        $entries = [];
        foreach ($document->children('http://www.sitemaps.org/schemas/sitemap/0.9')->url as $entry) {
            $entries[(string) $entry->loc] = hash('sha256', (string) $entry->asXML());
        }

        return $entries;
    }
}
