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
        return DB::transaction(function () use ($actor, $attributes, $reason): Promotion {
            $image = Arr::pull($attributes, 'image');
            $attributes['image_path'] = $this->storeImage($image);
            $promotion = $this->promotions->save(new Promotion($attributes));
            $this->auditLogs->record($actor, 'promotion.created', $promotion, after: $promotion->getAttributes(), reason: $reason);

            return $promotion;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Promotion $promotion, array $attributes, string $reason): Promotion
    {
        $oldImagePath = $promotion->image_path;

        $promotion = DB::transaction(function () use ($actor, $promotion, $attributes, $reason): Promotion {
            $before = $promotion->getAttributes();
            $image = Arr::pull($attributes, 'image');

            if ($image instanceof UploadedFile) {
                $attributes['image_path'] = $this->storeImage($image);
            }

            $promotion->fill($attributes);
            $this->promotions->save($promotion);
            $this->auditLogs->record($actor, 'promotion.updated', $promotion, $before, $promotion->getAttributes(), $reason);

            return $promotion;
        });

        if (isset($attributes['image']) && $oldImagePath !== null && $oldImagePath !== $promotion->image_path) {
            Storage::disk($this->mediaDisk())->delete($oldImagePath);
        }

        return $promotion;
    }

    private function storeImage(mixed $image): string
    {
        abort_unless($image instanceof UploadedFile, 422, 'A promotion image is required.');

        $path = $image->store('promotions', $this->mediaDisk());

        if ($path === false) {
            throw new RuntimeException('The promotion image could not be stored.');
        }

        return $path;
    }

    private function mediaDisk(): string
    {
        return (string) config('filesystems.media', 'public');
    }
}
