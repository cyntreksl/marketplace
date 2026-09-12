<?php

namespace App\Console\Commands;

use App\Contracts\GoogleSearchConsoleGateway;
use App\Exceptions\SeoMonitoringException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:submit-search-console-sitemap')]
#[Description('Submit the production root sitemap to Google Search Console')]
class SubmitSearchConsoleSitemap extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GoogleSearchConsoleGateway $searchConsole): int
    {
        if (! config('seo-monitoring.search_console.enabled')) {
            $this->info('Search Console sitemap submission is disabled.');

            return self::SUCCESS;
        }

        try {
            $searchConsole->submitSitemap();
            $this->info('Submitted https://prodeals.lk/sitemap.xml to sc-domain:prodeals.lk.');

            return self::SUCCESS;
        } catch (SeoMonitoringException $exception) {
            $this->warn($exception->getMessage());

            return self::FAILURE;
        }
    }
}
