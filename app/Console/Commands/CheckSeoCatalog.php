<?php

namespace App\Console\Commands;

use App\Services\SeoCatalogCheckService;
use App\Services\SeoMonitoringService;
use Illuminate\Console\Command;

class CheckSeoCatalog extends Command
{
    protected $signature = 'seo:check-catalog {--test-notification : Send a one-time delivery test after a successful catalog check}';

    protected $description = 'Validate public discovery XML against the eligible catalog';

    public function handle(SeoCatalogCheckService $check, SeoMonitoringService $monitor): int
    {
        $healthy = $monitor->run('catalog', fn (): array => $check->check());
        if ($healthy && $this->option('test-notification')) {
            $healthy = $monitor->sendTestNotification();
        }
        $this->line($healthy ? 'catalog check completed successfully.' : 'catalog check failed; inspect SEO monitoring logs and alerts.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
