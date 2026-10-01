<?php

namespace App\Console\Commands;

use App\Services\IndexNowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitIndexNow extends Command
{
    protected $signature = 'seo:submit-indexnow';

    protected $description = 'Submit sitemap URL changes to IndexNow, establishing a baseline on the first run';

    public function handle(IndexNowService $indexNow): int
    {
        try {
            $count = $indexNow->submitChanges();
        } catch (Throwable $exception) {
            Log::error('IndexNow submission failed; changes will be retried on the next run.', ['exception_type' => $exception::class]);
            $this->error('IndexNow submission failed; the saved snapshot was retained for retry.');

            return self::FAILURE;
        }

        $this->info('IndexNow: '.$count.' changed URLs submitted (first run establishes a baseline).');

        return self::SUCCESS;
    }
}
