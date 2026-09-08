<?php

namespace App\Services;

use App\Contracts\Repositories\SeoMonitoringRepository;
use App\Exceptions\SeoMonitoringException;
use App\Notifications\SeoMonitoringNotification;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SeoMonitoringService
{
    public function __construct(private readonly SeoMonitoringRepository $state) {}

    public function sendTestNotification(): bool
    {
        try {
            Notification::route('mail', config('seo-monitoring.alert_email'))
                ->notify(new SeoMonitoringNotification('catalog', [], test: true));

            return true;
        } catch (Throwable $exception) {
            Log::channel('seo-monitoring')->error('SEO monitoring test notification failed', ['exception_type' => $exception::class]);

            return false;
        }
    }

    /** @param Closure(): array{issues: list<string>, context: array<string, mixed>} $check */
    public function run(string $name, Closure $check): bool
    {
        try {
            return $this->state->synchronized($name, function () use ($name, $check): bool {
                try {
                    $result = $check();
                } catch (Throwable $exception) {
                    $result = [
                        'issues' => [$exception instanceof SeoMonitoringException ? $exception->getMessage() : 'The check could not complete. Review application connectivity and configuration.'],
                        'context' => ['exception_type' => $exception::class],
                    ];
                }

                $issues = array_values(array_unique($result['issues']));
                sort($issues);
                $previous = $this->state->get('alert:'.$name);
                if ($issues === [] && ($result['context']['reconciliation_pending'] ?? false) && ($previous['failed'] ?? false)) {
                    Log::channel('seo-monitoring')->info('SEO monitoring is awaiting import reconciliation', ['check' => $name, ...$result['context']]);

                    return true;
                }
                $fingerprint = hash('sha256', json_encode($issues, JSON_THROW_ON_ERROR));
                $failed = $issues !== [];
                $recovered = ! $failed && ($previous['failed'] ?? false);
                $notify = $recovered || ($failed && (
                    ($previous['fingerprint'] ?? null) !== $fingerprint
                    || ($previous['notified_at'] ?? 0) <= now()->subDays(7)->timestamp
                ));

                $context = [...$result['context'], 'check' => $name, 'issues' => $issues, 'checked_at' => now()->toIso8601String()];
                Log::channel('seo-monitoring')->log($failed ? 'error' : 'info', 'SEO monitoring check completed', $context);
                if ($notify) {
                    Notification::route('mail', config('seo-monitoring.alert_email'))
                        ->notify(new SeoMonitoringNotification($name, $issues, $recovered));
                }

                $this->state->put('alert:'.$name, [
                    'failed' => $failed,
                    'fingerprint' => $fingerprint,
                    'notified_at' => $notify ? now()->timestamp : ($previous['notified_at'] ?? null),
                    'result' => $context,
                ]);

                return ! $failed;
            }) === true;
        } catch (Throwable $exception) {
            Log::channel('seo-monitoring')->error('SEO monitoring infrastructure failed', ['check' => $name, 'exception_type' => $exception::class]);

            return false;
        }
    }
}
