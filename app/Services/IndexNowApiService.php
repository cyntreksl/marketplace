<?php

namespace App\Services;

use App\Contracts\IndexNowGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IndexNowApiService implements IndexNowGateway
{
    public function submit(string $keyLocation, array $urls): void
    {
        $response = Http::acceptJson()->connectTimeout(5)->timeout(20)
            ->post('https://api.indexnow.org/indexnow', [
                'host' => parse_url($keyLocation, PHP_URL_HOST),
                'key' => IndexNowService::KEY,
                'keyLocation' => $keyLocation,
                'urlList' => $urls,
            ]);

        if (! in_array($response->status(), [200, 202], true)) {
            throw new RuntimeException('IndexNow rejected the submission (HTTP '.$response->status().').');
        }
    }
}
