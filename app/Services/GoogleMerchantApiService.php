<?php

namespace App\Services;

use App\Contracts\GoogleMerchantGateway;
use App\Contracts\GoogleMerchantTokenProvider;
use App\Exceptions\SeoMonitoringException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class GoogleMerchantApiService implements GoogleMerchantGateway
{
    public function __construct(private readonly GoogleMerchantTokenProvider $tokens) {}

    /** @return array<string, mixed> */
    public function source(): array
    {
        return $this->get('');
    }

    /** @return array<string, mixed> */
    public function latestUpload(): array
    {
        return $this->get('/fileUploads/latest');
    }

    /** @return array<string, mixed> */
    private function get(string $suffix): array
    {
        $account = (string) config('seo-monitoring.merchant.account_id');
        $source = (string) config('seo-monitoring.merchant.source_id');
        if (! ctype_digit($account) || ! ctype_digit($source)) {
            throw new SeoMonitoringException('Merchant account and source IDs must be configured.');
        }

        $url = "https://merchantapi.googleapis.com/datasources/v1/accounts/{$account}/dataSources/{$source}{$suffix}";
        $token = $this->tokens->token();
        $refreshed = false;
        $retries = 0;

        while (true) {
            try {
                $response = Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(20)
                    ->withoutRedirecting()->get($url);
                if ($response->status() === 401 && ! $refreshed) {
                    $refreshed = true;
                    $token = $this->tokens->token(true);

                    continue;
                }

                if ($response->successful()) {
                    $data = $response->json();
                    if (! is_array($data) || $data === []) {
                        throw new SeoMonitoringException('Merchant API returned an invalid response.');
                    }

                    return $data;
                }

                if (! $response->serverError() && $response->status() !== 429) {
                    throw new SeoMonitoringException('Merchant API request failed (HTTP '.$response->status().'). Verify API access and source configuration.');
                }
            } catch (ConnectionException) {
                // Network failures share the bounded retry budget with transient HTTP errors.
            }

            if ($retries >= 3) {
                throw new SeoMonitoringException('Merchant API is unavailable after three retries.');
            }

            Sleep::for([250, 1000, 2000][$retries++])->milliseconds();
        }
    }
}
