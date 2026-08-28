<?php

namespace App\Services;

use App\Contracts\Repositories\PromotionRepository;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PromotionService
{
    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @return LengthAwarePaginator<int, Promotion> */
    public function adminPromotions(): LengthAwarePaginator
    {
        return $this->promotions->paginateForAdmin();
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes, string $reason): Promotion
    {
        $image = Arr::pull($attributes, 'image');
        $stored = $this->storeImage($image);
        $attributes['image_path'] = $stored['path'];
        $attributes['image_disk'] = $stored['disk'];

        try {
            return DB::transaction(function () use ($actor, $attributes, $reason): Promotion {
                $promotion = $this->promotions->save(new Promotion($attributes));
                $this->auditLogs->record($actor, 'promotion.created', $promotion, after: $promotion->getAttributes(), reason: $reason);

                return $promotion;
            });
        } catch (Throwable $exception) {
            Storage::disk($stored['disk'])->delete($stored['path']);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Promotion $promotion, array $attributes, string $reason): Promotion
    {
        $oldImagePath = $promotion->image_path;
        $oldImageDisk = $promotion->image_disk ?: $this->mediaDisk();
        $stored = null;

        try {
            $promotion = DB::transaction(function () use ($actor, $promotion, $attributes, $reason, &$stored): Promotion {
                $before = $promotion->getAttributes();
                $image = Arr::pull($attributes, 'image');

                if ($image instanceof UploadedFile) {
                    $stored = $this->storeImage($image);
                    $attributes['image_path'] = $stored['path'];
                    $attributes['image_disk'] = $stored['disk'];
                }

                $promotion->fill($attributes);
                $this->promotions->save($promotion);
                $this->auditLogs->record($actor, 'promotion.updated', $promotion, $before, $promotion->getAttributes(), $reason);

                return $promotion;
            });
        } catch (Throwable $exception) {
            if ($stored !== null) {
                Storage::disk($stored['disk'])->delete($stored['path']);
            }

            throw $exception;
        }

        if (isset($attributes['image']) && $oldImagePath !== null && $oldImagePath !== $promotion->image_path) {
            Storage::disk($oldImageDisk)->delete($oldImagePath);
        }

        return $promotion;
    }

    /** @return array{disk: string, path: string} */
    private function storeImage(mixed $image): array
    {
        abort_unless($image instanceof UploadedFile, 422, 'A promotion image is required.');

        $disk = $this->mediaDisk();
        $path = Storage::disk($disk)->putFileAs('promotions', $image, $image->hashName(), [
            'CacheControl' => 'public, max-age=31536000, immutable',
            'ContentType' => (string) $image->getMimeType(),
        ]);

        if ($path === false) {
            throw new RuntimeException('The promotion image could not be stored.');
        }

        return ['disk' => $disk, 'path' => $path];
    }

    private function mediaDisk(): string
    {
        return (string) config('filesystems.media', 'public');
    }
}
