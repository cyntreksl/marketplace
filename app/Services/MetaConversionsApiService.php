<?php

namespace App\Services;

use App\Contracts\MetaConversionsGateway;
use App\Support\MetaConversionEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MetaConversionsApiService implements MetaConversionsGateway
{
    public function send(MetaConversionEvent $event, ?string $testEventCode = null): void
    {
        $pixelId = (string) config('services.meta_conversions.pixel_id');
        $accessToken = (string) config('services.meta_conversions.access_token');
        $apiVersion = (string) config('services.meta_conversions.api_version', 'v25.0');

        if ($pixelId === '' || $accessToken === '') {
            throw new RuntimeException('Meta Conversions API credentials are not configured.');
        }

        $payload = ['data' => [$event->toArray()]];

        if (filled($testEventCode)) {
            $payload['test_event_code'] = $testEventCode;
        }

        $this->request($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$pixelId}/events", $payload)
            ->throw();
    }

    private function request(#[\SensitiveParameter] string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(
                [100, 500, 1000],
                when: static function (Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError());
                },
            );
    }
}
