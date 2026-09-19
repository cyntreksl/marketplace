<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Services\CollectionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('collections:generate-open-graph-images')]
#[Description('Generate uncropped 1200x630 social share images for every collection with artwork')]
class GenerateCollectionOpenGraphImages extends Command
{
    public function handle(CollectionService $collections): int
    {
        $failed = 0;

        Collection::query()->orderBy('id')->each(function (Collection $collection) use ($collections, &$failed): void {
            try {
                $collections->refreshOpenGraphImage($collection);
                $this->line("{$collection->slug}: ".($collection->open_graph_image_path ?? 'no artwork'));
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$collection->slug}: {$exception->getMessage()}");
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
