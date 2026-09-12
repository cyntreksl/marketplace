<?php

namespace App\Services;

use App\Contracts\GoogleSearchConsoleTokenProvider;
use App\Exceptions\SeoMonitoringException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class GoogleSearchConsoleTokenService implements GoogleSearchConsoleTokenProvider
{
    private ?string $accessToken = null;

    public function token(bool $refresh = false): string
    {
        if ($this->accessToken !== null && ! $refresh) {
            return $this->accessToken;
        }

        $path = config('seo-monitoring.search_console.credentials_path');
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new SeoMonitoringException('Search Console credentials are missing or unreadable.');
        }

        try {
            $credentials = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (($credentials['type'] ?? null) !== 'service_account') {
                throw new SeoMonitoringException('Search Console requires service account credentials.');
            }

            $auth = new ServiceAccountCredentials('https://www.googleapis.com/auth/webmasters', $credentials);
            $token = $auth->fetchAuthToken(function (RequestInterface $request): ResponseInterface {
                return Http::connectTimeout(5)->timeout(20)
                    ->retry([250, 1000, 2000], when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429)))
                    ->withHeaders($request->getHeaders())
                    ->withBody((string) $request->getBody(), 'application/x-www-form-urlencoded')
                    ->post('https://oauth2.googleapis.com/token')->throw()->toPsrResponse();
            });

            if (! is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
                throw new SeoMonitoringException('Search Console returned no access token.');
            }

            return $this->accessToken = $token['access_token'];
        } catch (Throwable) {
            throw new SeoMonitoringException('Search Console authentication failed. Verify the service account credentials and property access.');
        }
    }
}
