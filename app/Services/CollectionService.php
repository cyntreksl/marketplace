<?php

namespace App\Services;

use App\Contracts\Repositories\CollectionRepository;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CollectionService
{
    public function __construct(
        private readonly CollectionRepository $collections,
        private readonly AuditLogService $auditLogs,
        private readonly CollectionArtworkService $artwork,
        private readonly SeoRedirectService $redirects,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes, string $reason): Collection
    {
        $listingIds = Arr::pull($attributes, 'listing_ids', []);
        $attributes['slug'] = Arr::get($attributes, 'slug') ?: Str::slug($attributes['name']);

        return DB::transaction(function () use ($actor, $attributes, $listingIds, $reason): Collection {
            $collection = $this->collections->save(new Collection($attributes));
            $this->syncListings($collection, $listingIds);
            $this->auditLogs->record($actor, 'collection.created', $collection, null, $collection->getAttributes(), $reason);

            return $collection;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Collection $collection, array $attributes, string $reason): Collection
    {
        $oldSlug = $collection->slug;
        $listingIds = Arr::pull($attributes, 'listing_ids', null);
        $attributes['slug'] = Arr::get($attributes, 'slug') ?: Str::slug($attributes['name']);

        return DB::transaction(function () use ($actor, $collection, $attributes, $listingIds, $reason, $oldSlug): Collection {
            $before = $collection->getAttributes();
            $collection->fill($attributes);
            $this->collections->save($collection);

            if ($collection->isManual() && is_array($listingIds)) {
                $this->syncListings($collection, $listingIds);
            }

            $this->auditLogs->record($actor, 'collection.updated', $collection, $before, $collection->getAttributes(), $reason);
            $this->redirects->recordSlugChange('collections', $oldSlug, $collection->slug);

            return $collection;
        });
    }

    public function updateActivation(User $actor, Collection $collection, bool $isActive, string $reason): Collection
    {
        return DB::transaction(function () use ($actor, $collection, $isActive, $reason): Collection {
            $before = $collection->getAttributes();
            $collection->forceFill(['is_active' => $isActive]);
            $this->collections->save($collection);
            $this->auditLogs->record($actor, 'collection.activation_updated', $collection, $before, $collection->getAttributes(), $reason);

            return $collection;
        });
    }

    public function archive(User $actor, Collection $collection, string $reason): void
    {
        $before = $collection->getAttributes();
        $this->collections->delete($collection);
        $this->auditLogs->record($actor, 'collection.archived', $collection, $before, $collection->getAttributes(), $reason);
    }

    public function restore(User $actor, Collection $collection, string $reason): void
    {
        $before = $collection->getAttributes();
        $this->collections->restore($collection);
        $this->auditLogs->record($actor, 'collection.restored', $collection, $before, $collection->getAttributes(), $reason);
    }

    /** @param array{x: int, y: int, width: int, height: int} $crop */
    public function replaceImage(User $actor, Collection $collection, UploadedFile $image, array $crop, string $reason): Collection
    {
        return $this->replaceArtwork($actor, $collection, $image, $crop, $reason, 'tile', 'image_path', 'image_disk');
    }

    public function removeImage(User $actor, Collection $collection, string $reason): Collection
    {
        return $this->removeArtwork($actor, $collection, $reason, 'image_path', 'image_disk', 'collection.image_removed');
    }

    /** @param array{x: int, y: int, width: int, height: int} $crop */
    public function replaceBannerImage(User $actor, Collection $collection, UploadedFile $image, array $crop, string $reason): Collection
    {
        return $this->replaceArtwork($actor, $collection, $image, $crop, $reason, 'banner', 'banner_image_path', 'banner_image_disk');
    }

    public function removeBannerImage(User $actor, Collection $collection, string $reason): Collection
    {
        return $this->removeArtwork($actor, $collection, $reason, 'banner_image_path', 'banner_image_disk', 'collection.banner_image_removed');
    }

    /** @param array{x: int, y: int, width: int, height: int} $crop */
    public function replaceVerticalImage(User $actor, Collection $collection, UploadedFile $image, array $crop, string $reason): Collection
    {
        return $this->replaceArtwork($actor, $collection, $image, $crop, $reason, 'vertical', 'vertical_image_path', 'vertical_image_disk');
    }

    public function removeVerticalImage(User $actor, Collection $collection, string $reason): Collection
    {
        return $this->removeArtwork($actor, $collection, $reason, 'vertical_image_path', 'vertical_image_disk', 'collection.vertical_image_removed');
    }

    public function refreshOpenGraphImage(Collection $collection): Collection
    {
        $oldPath = $collection->open_graph_image_path;
        $oldDisk = $collection->open_graph_image_disk;
        $stored = $this->artwork->storeOpenGraph($collection);

        $collection->forceFill([
            'open_graph_image_path' => $stored['path'] ?? null,
            'open_graph_image_disk' => $stored['disk'] ?? null,
        ]);
        $this->collections->save($collection);

        if (is_string($oldPath) && $oldPath !== ($stored['path'] ?? null)) {
            $this->artwork->delete(is_string($oldDisk) ? $oldDisk : null, $oldPath);
        }

        return $collection;
    }

    /** A failed share image must never block an artwork change; the storefront falls back to the raw artwork. */
    private function refreshOpenGraphImageSafely(Collection $collection): void
    {
        try {
            $this->refreshOpenGraphImage($collection);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<int, int>  $listingIds
     *
     * Detaches everything and reattaches fresh rather than using sync(), because sync()
     * updates/attaches pivot rows one at a time and can transiently collide with the
     * unique(collection_id, position) constraint when reordering listings that keep
     * overlapping position values.
     */
    private function syncListings(Collection $collection, array $listingIds): void
    {
        $collection->listings()->detach();
        $collection->listings()->attach(collect($listingIds)->values()->mapWithKeys(
            fn (int $listingId, int $position): array => [$listingId => ['position' => $position]],
        )->all());
    }

    /** @param array{x: int, y: int, width: int, height: int} $crop */
    private function replaceArtwork(
        User $actor,
        Collection $collection,
        UploadedFile $image,
        array $crop,
        string $reason,
        string $type,
        string $pathAttribute,
        string $diskAttribute,
    ): Collection {
        $auditAction = match ($type) {
            'banner' => 'collection.banner_image_updated',
            'vertical' => 'collection.vertical_image_updated',
            default => 'collection.image_updated',
        };
        $oldPath = $collection->getAttribute($pathAttribute);
        $oldDisk = $collection->getAttribute($diskAttribute);
        $stored = $this->artwork->store($collection, $image, $crop, $type);

        try {
            $collection = DB::transaction(function () use ($actor, $collection, $pathAttribute, $diskAttribute, $auditAction, $stored, $reason): Collection {
                $before = $collection->getAttributes();
                $collection->forceFill([
                    $pathAttribute => $stored['path'],
                    $diskAttribute => $stored['disk'],
                ]);
                $this->collections->save($collection);
                $this->auditLogs->record($actor, $auditAction, $collection, $before, $collection->getAttributes(), $reason);

                return $collection;
            });
        } catch (Throwable $exception) {
            $this->artwork->delete($stored['disk'], $stored['path']);

            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== $stored['path']) {
            $this->artwork->delete(is_string($oldDisk) ? $oldDisk : null, $oldPath);
        }

        $this->refreshOpenGraphImageSafely($collection);

        return $collection;
    }

    private function removeArtwork(User $actor, Collection $collection, string $reason, string $pathAttribute, string $diskAttribute, string $auditAction): Collection
    {
        $oldPath = $collection->getAttribute($pathAttribute);
        $oldDisk = $collection->getAttribute($diskAttribute);

        $collection = DB::transaction(function () use ($actor, $collection, $pathAttribute, $diskAttribute, $auditAction, $reason): Collection {
            $before = $collection->getAttributes();
            $collection->forceFill([
                $pathAttribute => null,
                $diskAttribute => null,
            ]);
            $this->collections->save($collection);
            $this->auditLogs->record($actor, $auditAction, $collection, $before, $collection->getAttributes(), $reason);

            return $collection;
        });

        if (is_string($oldPath)) {
            $this->artwork->delete(is_string($oldDisk) ? $oldDisk : null, $oldPath);
        }

        $this->refreshOpenGraphImageSafely($collection);

        return $collection;
    }
}
