<?php

namespace App\Services;

use App\Contracts\GoogleMerchantGateway;
use App\Contracts\Repositories\SeoMonitoringRepository;
use App\Exceptions\SeoMonitoringException;
use Carbon\CarbonImmutable;
use Throwable;

class SeoMerchantCheckService
{
    public function __construct(
        private readonly GoogleMerchantGateway $google,
        private readonly SeoCatalogCheckService $catalog,
        private readonly SeoMonitoringRepository $state,
    ) {}

    /** @return array{issues: list<string>, context: array<string, mixed>} */
    public function check(): array
    {
        $source = $this->google->source();
        $upload = $this->google->latestUpload();
        $issues = $this->sourceIssues($source);
        $processing = $upload['processingState'] ?? '';
        $uploadTime = $this->uploadTime($upload['uploadTime'] ?? null);
        $itemCount = (string) ($upload['itemsTotal'] ?? '0');
        if (! ctype_digit($itemCount) || ! in_array($processing, ['SUCCEEDED', 'IN_PROGRESS', 'FAILED'], true)) {
            throw new SeoMonitoringException('Merchant API returned incomplete processing details.');
        }

        if ($processing === 'FAILED') {
            $issues[] = 'Google could not process the catalog import.';
        }

        $hasImportErrors = false;
        $warnings = [];
        foreach (($upload['issues'] ?? []) as $issue) {
            $code = preg_replace('/[^a-zA-Z0-9_\\/.-]/', '', (string) ($issue['code'] ?? 'unknown'));
            $count = max(0, (int) ($issue['count'] ?? 0));
            if (($issue['severity'] ?? '') === 'ERROR') {
                $hasImportErrors = true;
                $issues[] = 'Google import error '.$code.' affects '.$count.' item(s).';
            } else {
                $warnings[] = $code;
            }
        }

        $history = $this->state->get('merchant-history') ?? ['first_seen' => now()->timestamp];
        if ($processing === 'SUCCEEDED' && ! $hasImportErrors) {
            $history['last_success'] = max($history['last_success'] ?? 0, $uploadTime);
        }
        $this->state->put('merchant-history', $history);
        $lastSuccess = $history['last_success'] ?? min($history['first_seen'], $uploadTime);
        if ($lastSuccess < now()->subHours(30)->timestamp) {
            $issues[] = 'No successful catalog import has been confirmed within 30 hours.';
        }

        $baseline = $this->state->get('catalog-baseline');
        $current = $this->catalog->snapshot();
        $canReconcile = $processing === 'SUCCEEDED'
            && $baseline !== null
            && $baseline['captured_at'] <= $uploadTime
            && $baseline['captured_at'] >= now()->subHours(30)->timestamp
            && $baseline['fingerprint'] === $current['fingerprint'];

        if ($baseline === null || $baseline['captured_at'] < now()->subHours(30)->timestamp) {
            $issues[] = 'No recent successful catalog check is available for import reconciliation.';
        }
        if ($canReconcile && (int) $itemCount !== $baseline['count']) {
            $issues[] = 'Google processed '.$itemCount.' offers; the unchanged catalog contains '.$baseline['count'].'.';
        }

        return [
            'issues' => $issues,
            'context' => [
                'processing_state' => $processing,
                'upload_time' => $uploadTime,
                'processed_offer_count' => (int) $itemCount,
                'current_offer_count' => $current['count'],
                'reconciliation_pending' => ! $canReconcile,
                'warning_codes' => $warnings,
            ],
        ];
    }

    /** @param array<string, mixed> $source
     * @return list<string>
     */
    private function sourceIssues(array $source): array
    {
        $settings = $source['fileInput']['fetchSettings'] ?? [];
        $issues = [];
        if (($source['input'] ?? '') !== 'FILE' || ($source['fileInput']['fileInputType'] ?? '') !== 'FETCH'
            || ($settings['enabled'] ?? false) !== true) {
            $issues[] = 'The daily XML fetch source is disabled or no longer configured as a URL source.';
        }
        if (($settings['frequency'] ?? '') !== 'FREQUENCY_DAILY'
            || ($settings['timeZone'] ?? '') !== 'Asia/Colombo'
            || ($settings['timeOfDay']['hours'] ?? -1) !== 2
            || ($settings['timeOfDay']['minutes'] ?? 0) !== 0
            || ($settings['timeOfDay']['seconds'] ?? 0) !== 0) {
            $issues[] = 'The Merchant fetch schedule differs from daily 02:00 Asia/Colombo.';
        }
        if (($settings['fetchUri'] ?? '') !== route('feeds.google_merchant')) {
            $issues[] = 'Merchant Center is fetching a different catalog URL.';
        }
        $primary = $source['primaryProductDataSource'] ?? [];
        if (($primary['contentLanguage'] ?? '') !== 'en'
            || ($primary['feedLabel'] ?? '') !== 'LK'
            || ($primary['countries'] ?? []) !== ['LK']) {
            $issues[] = 'The Merchant source no longer matches English and Sri Lanka targeting.';
        }

        return $issues;
    }

    private function uploadTime(mixed $value): int
    {
        try {
            if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}T/', $value)) {
                throw new SeoMonitoringException('Invalid timestamp.');
            }
            $timestamp = CarbonImmutable::parse($value)->getTimestamp();
            if ($timestamp > now()->addMinutes(5)->timestamp) {
                throw new SeoMonitoringException('Future timestamp.');
            }

            return $timestamp;
        } catch (Throwable) {
            throw new SeoMonitoringException('Merchant API returned an invalid upload timestamp.');
        }
    }
}
