<?php

namespace App\Console\Commands;

use App\Services\SeoMerchantCheckService;
use App\Services\SeoMonitoringService;
use Illuminate\Console\Command;

class CheckSeoMerchant extends Command
{
    protected $signature = 'seo:check-merchant';

    protected $description = 'Check Google Merchant source scheduling and import health';

    public function handle(SeoMerchantCheckService $check, SeoMonitoringService $monitor): int
    {
        $healthy = $monitor->run('merchant', fn (): array => $check->check());
        $this->line($healthy ? 'merchant check completed successfully.' : 'merchant check failed; inspect SEO monitoring logs and alerts.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
