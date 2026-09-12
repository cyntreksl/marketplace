<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

#[Signature('seo:disable-cloudflare-managed-robots')]
#[Description('Disable Cloudflare Managed Robots so the application robots.txt is authoritative')]
class DisableCloudflareManagedRobots extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $zoneId = config('seo-monitoring.cloudflare.zone_id');
        $apiToken = config('seo-monitoring.cloudflare.api_token');

        if (! is_string($zoneId) || $zoneId === '' || ! is_string($apiToken) || $apiToken === '') {
            $this->info('Cloudflare credentials are not configured; managed robots check skipped.');

            return self::SUCCESS;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($apiToken)
                ->connectTimeout(5)
                ->timeout(20)
                ->withoutRedirecting()
                ->put("https://api.cloudflare.com/client/v4/zones/{$zoneId}/bot_management", [
                    'is_robots_txt_managed' => false,
                ]);
        } catch (ConnectionException) {
            $this->error('Cloudflare Managed Robots could not be disabled because the API was unavailable.');

            return self::FAILURE;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $this->error('Cloudflare Managed Robots could not be disabled. Verify the zone and token permissions.');

            return self::FAILURE;
        }

        $this->info('Cloudflare Managed Robots is disabled.');

        return self::SUCCESS;
    }
}
