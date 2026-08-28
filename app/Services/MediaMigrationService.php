<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\PromotionRepository;
use App\Models\Category;
use App\Models\ListingMedia;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaMigrationService
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly ListingRepository $listings,
        private readonly PromotionRepository $promotions,
        private readonly StaticMediaService $staticMedia,
    ) {}

    /**
     * @return array{examined: int, copied: int, skipped: int, planned: int, records_updated: int, static_copied: int}
     */
    public function migrate(string $fallbackSourceDisk, string $destinationDisk, bool $dryRun): array
    {
        $stats = [
            'examined' => 0,
            'copied' => 0,
            'skipped' => 0,
            'planned' => 0,
            'records_updated' => 0,
            'static_copied' => 0,
        ];

        $this->listings->mediaForMigration()->each(function (ListingMedia $media) use ($fallbackSourceDisk, $destinationDisk, $dryRun, &$stats): void {
            $sourceDisk = $media->disk ?: $fallbackSourceDisk;
            $paths = collect([
                $media->path,
                $media->source_path,
                ...array_values(is_array($media->variants) ? $media->variants : []),
            ])->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
                ->unique()
                ->values();

            foreach ($paths as $path) {
                $this->copyStorageObject($sourceDisk, $destinationDisk, $path, $dryRun, $stats);
            }
        });

        $this->catalog->categoryArtworkForMigration()->each(function (Category $category) use ($fallbackSourceDisk, $destinationDisk, $dryRun, &$stats): void {
            $this->copyCategoryArtwork($category, 'image_path', 'image_disk', $fallbackSourceDisk, $destinationDisk, $dryRun, $stats);
            $this->copyCategoryArtwork($category, 'banner_image_path', 'banner_image_disk', $fallbackSourceDisk, $destinationDisk, $dryRun, $stats);
        });

        $this->promotions->forMediaMigration()->each(function (Promotion $promotion) use ($fallbackSourceDisk, $destinationDisk, $dryRun, &$stats): void {
            $sourceDisk = $promotion->image_disk ?: $fallbackSourceDisk;
            $this->copyStorageObject($sourceDisk, $destinationDisk, $promotion->image_path, $dryRun, $stats);
        });

        foreach (StaticMediaService::ASSETS as $asset) {
            $result = $this->copyStaticAsset($asset, $destinationDisk, $dryRun, $stats);

            if ($result === 'copied') {
                $stats['static_copied']++;
            }
        }

        if (! $dryRun) {
            $stats['records_updated'] = $this->updateDiskOwnership($fallbackSourceDisk, $destinationDisk);
        }

        return $stats;
    }

    /**
     * @param  array{examined: int, copied: int, skipped: int, planned: int, records_updated: int, static_copied: int}  $stats
     */
    private function copyCategoryArtwork(
        Category $category,
        string $pathAttribute,
        string $diskAttribute,
        string $fallbackSourceDisk,
        string $destinationDisk,
        bool $dryRun,
        array &$stats,
    ): void {
        $path = $category->getAttribute($pathAttribute);

        if (! is_string($path) || $path === '') {
            return;
        }

        $storedDisk = $category->getAttribute($diskAttribute);
        $sourceDisk = is_string($storedDisk) && $storedDisk !== '' ? $storedDisk : $fallbackSourceDisk;
        $this->copyStorageObject($sourceDisk, $destinationDisk, $path, $dryRun, $stats);
    }

    private function updateDiskOwnership(string $fallbackSourceDisk, string $destinationDisk): int
    {
        return DB::transaction(function () use ($fallbackSourceDisk, $destinationDisk): int {
            $updated = 0;

            $this->listings->mediaForMigration()->each(function (ListingMedia $media) use ($fallbackSourceDisk, $destinationDisk, &$updated): void {
                if (($media->disk ?: $fallbackSourceDisk) === $destinationDisk) {
                    return;
                }

                $media->forceFill(['disk' => $destinationDisk]);
                $this->listings->saveMedia($media);
                $updated++;
            });

            $this->catalog->categoryArtworkForMigration()->each(function (Category $category) use ($fallbackSourceDisk, $destinationDisk, &$updated): void {
                $changed = false;

                foreach ([
                    ['image_path', 'image_disk'],
                    ['banner_image_path', 'banner_image_disk'],
                ] as [$pathAttribute, $diskAttribute]) {
                    $path = $category->getAttribute($pathAttribute);
                    $storedDisk = $category->getAttribute($diskAttribute);
                    $sourceDisk = is_string($storedDisk) && $storedDisk !== '' ? $storedDisk : $fallbackSourceDisk;

                    if (is_string($path) && $path !== '' && $sourceDisk !== $destinationDisk) {
                        $category->forceFill([$diskAttribute => $destinationDisk]);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $this->catalog->saveCategory($category);
                    $updated++;
                }
            });

            $this->promotions->forMediaMigration()->each(function (Promotion $promotion) use ($fallbackSourceDisk, $destinationDisk, &$updated): void {
                if (($promotion->image_disk ?: $fallbackSourceDisk) === $destinationDisk) {
                    return;
                }

                $promotion->forceFill(['image_disk' => $destinationDisk]);
                $this->promotions->save($promotion);
                $updated++;
            });

            return $updated;
        });
    }

    /**
     * @param  array{examined: int, copied: int, skipped: int, planned: int, records_updated: int, static_copied: int}  $stats
     */
    private function copyStorageObject(
        string $sourceDisk,
        string $destinationDisk,
        string $path,
        bool $dryRun,
        array &$stats,
    ): string {
        $source = Storage::disk($sourceDisk);
        $destination = Storage::disk($destinationDisk);
        $stats['examined']++;

        if (! $source->exists($path)) {
            throw new RuntimeException("Missing media object {$sourceDisk}:{$path}.");
        }

        $sourceSize = $source->size($path);

        if ($destination->exists($path) && $destination->size($path) === $sourceSize) {
            $stats['skipped']++;

            return 'skipped';
        }

        if ($dryRun) {
            $stats['planned']++;

            return 'planned';
        }

        $stream = $source->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read media object {$sourceDisk}:{$path}.");
        }

        try {
            $mimeType = $source->mimeType($path);
            $stored = $destination->writeStream($path, $stream, [
                'CacheControl' => self::CACHE_CONTROL,
                'ContentType' => is_string($mimeType) ? $mimeType : 'application/octet-stream',
            ]);
        } finally {
            fclose($stream);
        }

        if ($stored === false || ! $destination->exists($path) || $destination->size($path) !== $sourceSize) {
            throw new RuntimeException("Unable to verify migrated media object {$destinationDisk}:{$path}.");
        }

        $stats['copied']++;

        return 'copied';
    }

    /**
     * @param  array{examined: int, copied: int, skipped: int, planned: int, records_updated: int, static_copied: int}  $stats
     */
    private function copyStaticAsset(string $asset, string $destinationDisk, bool $dryRun, array &$stats): string
    {
        $sourcePath = public_path($asset);
        $destinationPath = $this->staticMedia->objectPath($asset);
        $destination = Storage::disk($destinationDisk);
        $stats['examined']++;

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Missing static media asset {$asset}.");
        }

        $sourceSize = filesize($sourcePath);

        if ($sourceSize === false) {
            throw new RuntimeException("Unable to inspect static media asset {$asset}.");
        }

        if ($destination->exists($destinationPath) && $destination->size($destinationPath) === $sourceSize) {
            $stats['skipped']++;

            return 'skipped';
        }

        if ($dryRun) {
            $stats['planned']++;

            return 'planned';
        }

        $stream = fopen($sourcePath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read static media asset {$asset}.");
        }

        try {
            $mimeType = mime_content_type($sourcePath);
            $stored = $destination->writeStream($destinationPath, $stream, [
                'CacheControl' => self::CACHE_CONTROL,
                'ContentType' => is_string($mimeType) ? $mimeType : 'application/octet-stream',
            ]);
        } finally {
            fclose($stream);
        }

        if ($stored === false || ! $destination->exists($destinationPath) || $destination->size($destinationPath) !== $sourceSize) {
            throw new RuntimeException("Unable to verify static media asset {$destinationPath}.");
        }

        $stats['copied']++;

        return 'copied';
    }
}
