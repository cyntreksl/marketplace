<?php

namespace App\Services;

use App\Contracts\MetaConversionsGateway;
use App\Support\MetaConversionEvent;
use App\Support\MetaConversionReceipt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MetaConversionsApiService implements MetaConversionsGateway
{
    public function send(MetaConversionEvent $event, ?string $testEventCode = null): MetaConversionReceipt
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

        $response = $this->request($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$pixelId}/events", $payload)
            ->throw();

        $eventsReceived = $response->json('events_received');

        if (! is_int($eventsReceived) || $eventsReceived !== 1) {
            throw new RuntimeException('Meta Conversions API did not acknowledge exactly one event.');
        }

        return new MetaConversionReceipt(
            eventsReceived: $eventsReceived,
            fbtraceId: $this->sanitizedTraceId($response->json('fbtrace_id')),
            messages: $this->sanitizedMessages($response->json('messages')),
        );
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

    private function sanitizedTraceId(mixed $traceId): ?string
    {
        if (! is_string($traceId) || ! preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $traceId)) {
            return null;
        }

        return $traceId;
    }

    /** @return list<string> */
    private function sanitizedMessages(mixed $messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $sanitized = [];

        foreach (array_slice($messages, 0, 5) as $message) {
            $value = is_string($message) ? $message : data_get($message, 'message');

            if (! is_string($value)) {
                continue;
            }

            $value = preg_replace([
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '/\b(?:\+?\d[\s().-]*){7,}\b/',
                '/\b(?:access[_ -]?token|token|authorization|bearer)\b\s*[:=]?\s*\S+/i',
            ], '[redacted]', Str::squish($value));

            if (is_string($value) && $value !== '') {
                $sanitized[] = Str::limit($value, 200, '');
            }
        }

        return $sanitized;
    }
}
