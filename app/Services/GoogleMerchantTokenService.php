<?php

namespace App\Services;

use App\Contracts\GoogleMerchantTokenProvider;
use App\Exceptions\SeoMonitoringException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class GoogleMerchantTokenService implements GoogleMerchantTokenProvider
{
    private ?string $accessToken = null;

    public function token(bool $refresh = false): string
    {
        if ($this->accessToken !== null && ! $refresh) {
            return $this->accessToken;
        }

        $path = config('seo-monitoring.merchant.credentials_path');
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new SeoMonitoringException('Merchant API credentials are missing or unreadable.');
        }

        try {
            $credentials = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (($credentials['type'] ?? null) !== 'service_account') {
                throw new SeoMonitoringException('Merchant API requires service account credentials.');
            }

            $auth = new ServiceAccountCredentials('https://www.googleapis.com/auth/content', $credentials);
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
                throw new SeoMonitoringException('Merchant API returned no access token.');
            }

            return $this->accessToken = $token['access_token'];
        } catch (Throwable) {
            throw new SeoMonitoringException('Merchant API authentication failed. Verify the service account credentials and account access.');
        }
    }
}
