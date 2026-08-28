<?php

namespace App\Console\Commands;

use App\Services\MediaMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('media:migrate-to-r2 {--source=public : Fallback disk for legacy records} {--destination=r2 : Destination media disk} {--dry-run : Validate and report without copying or updating records}')]
#[Description('Copy public runtime images to Cloudflare R2 and update their storage disks')]
class MigrateMediaToR2 extends Command
{
    public function handle(MediaMigrationService $migration): int
    {
        $source = (string) $this->option('source');
        $destination = (string) $this->option('destination');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $stats = $migration->migrate($source, $destination, $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], collect($stats)
            ->map(fn (int $count, string $metric): array => [str_replace('_', ' ', ucfirst($metric)), $count])
            ->values()
            ->all());
        $this->info($dryRun ? 'Dry run completed. No files or records were changed.' : 'Media migration completed. Source files were retained.');

        return self::SUCCESS;
    }
}
