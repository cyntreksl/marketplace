<?php

namespace App\Console\Commands;

use App\Services\SeoGrowthContentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:seed-growth-content')]
#[Description('Idempotently seed the approved initial SEO commercial content and buying guides')]
class SeedSeoGrowthContent extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SeoGrowthContentService $content): int
    {
        $counts = $content->seed();

        foreach ($counts as $type => $count) {
            $this->line(ucfirst($type).": {$count}");
        }

        return self::SUCCESS;
    }
}
