<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GoogleProductTaxonomyVersion;
use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AdminCatalogService
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly AuditLogService $auditLogs,
        private readonly CategoryArtworkService $artwork,
    ) {}

    /** @return array<string, array{group: string, label: string, min: int}> */
    public static function settingDefinitions(): array
    {
        return [
            'auction.default_duration_days' => ['group' => 'auction', 'label' => 'Default auction duration (days)', 'min' => 1],
            'auction.anti_sniping_extension_minutes' => ['group' => 'auction', 'label' => 'Anti-sniping extension (minutes)', 'min' => 0],
            'auction.winner_payment_deadline_hours' => ['group' => 'auction', 'label' => 'Winner payment deadline (hours)', 'min' => 1],
            'checkout.cod_maximum_amount' => ['group' => 'checkout', 'label' => 'COD maximum amount', 'min' => 0],
            'settlement.hold_days' => ['group' => 'settlement', 'label' => 'Settlement hold (days)', 'min' => 0],
            'settlement.minimum_payout_amount' => ['group' => 'settlement', 'label' => 'Minimum payout amount', 'min' => 0],
            'commission.default_percentage' => ['group' => 'commission', 'label' => 'Default commission percentage', 'min' => 0],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createCategory(User $actor, array $attributes, string $reason): Category
    {
        $image = Arr::pull($attributes, 'image');
        $imageCrop = $this->categoryArtworkCrop(Arr::pull($attributes, 'image_crop'));
        $bannerImage = Arr::pull($attributes, 'banner_image');
        $bannerImageCrop = $this->categoryArtworkCrop(Arr::pull($attributes, 'banner_image_crop'));
        $attributes['slug'] = $attributes['slug'] ?: Str::slug($attributes['name']);
        $storedArtwork = [];

        try {
            return DB::transaction(function () use ($actor, $attributes, $image, $imageCrop, $bannerImage, $bannerImageCrop, $reason, &$storedArtwork): Category {
                $category = $this->catalog->saveCategory(new Category($attributes));

                if ($image instanceof UploadedFile && $imageCrop !== null) {
                    $storedImage = $this->artwork->store($category, $image, $imageCrop, 'image');
                    $storedArtwork[] = $storedImage;
                    $category->forceFill([
                        'image_path' => $storedImage['path'],
                        'image_disk' => $storedImage['disk'],
                    ]);
                }

                if ($bannerImage instanceof UploadedFile && $bannerImageCrop !== null) {
                    $storedBanner = $this->artwork->store($category, $bannerImage, $bannerImageCrop, 'banner');
                    $storedArtwork[] = $storedBanner;
                    $category->forceFill([
                        'banner_image_path' => $storedBanner['path'],
                        'banner_image_disk' => $storedBanner['disk'],
                    ]);
                }

                if ($storedArtwork !== []) {
                    $this->catalog->saveCategory($category);
                }

                $this->auditLogs->record($actor, 'category.created', $category, null, $category->getAttributes(), $reason);

                return $category;
            });
        } catch (Throwable $exception) {
            foreach ($storedArtwork as $stored) {
                $this->artwork->delete($stored['disk'], $stored['path']);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function updateCategory(User $actor, Category $category, array $attributes, string $reason): Category
    {
        $attributes['slug'] = $attributes['slug'] ?: Str::slug($attributes['name']);

        return DB::transaction(function () use ($actor, $category, $attributes, $reason): Category {
            $before = $category->getAttributes();
            $category->fill($attributes);
            $this->catalog->saveCategory($category);
            $this->auditLogs->record($actor, 'category.updated', $category, $before, $category->getAttributes(), $reason);

            return $category;
        });
    }

    /** @param array{x: int, y: int, width: int, height: int} $crop */
    public function replaceCategoryImage(User $actor, Category $category, UploadedFile $image, array $crop, string $reason): Category
    {
        return $this->replaceCategoryArtwork($actor, $category, $image, $crop, $reason, 'image');
    }

    public function removeCategoryImage(User $actor, Category $category, string $reason): Category
    {
        return $this->removeCategoryArtwork($actor, $category, $reason, 'image');
    }

    /** @param array{x: int, y: int, width: int, height: int} $crop */
    public function replaceCategoryBannerImage(User $actor, Category $category, UploadedFile $image, array $crop, string $reason): Category
    {
        return $this->replaceCategoryArtwork($actor, $category, $image, $crop, $reason, 'banner');
    }

    public function removeCategoryBannerImage(User $actor, Category $category, string $reason): Category
    {
        return $this->removeCategoryArtwork($actor, $category, $reason, 'banner');
    }

    /** @return array{x: int, y: int, width: int, height: int}|null */
    private function categoryArtworkCrop(mixed $crop): ?array
    {
        if (! is_array($crop)
            || ! is_int($crop['x'] ?? null)
            || ! is_int($crop['y'] ?? null)
            || ! is_int($crop['width'] ?? null)
            || ! is_int($crop['height'] ?? null)) {
            return null;
        }

        return [
            'x' => $crop['x'],
            'y' => $crop['y'],
            'width' => $crop['width'],
            'height' => $crop['height'],
        ];
    }

    public function updateCategoryActivation(User $actor, Category $category, bool $isActive, string $reason): void
    {
        DB::transaction(function () use ($actor, $category, $isActive, $reason): void {
            $activationRoot = $isActive
                ? $this->catalog->categoryActivationRoot($category)
                : $category;
            $affectedCategories = $this->catalog->setCategorySubtreeActive($activationRoot, $isActive);

            $this->auditLogs->record(
                $actor,
                $isActive ? 'category.subtree_activated' : 'category.subtree_deactivated',
                $category,
                ['is_active' => $category->is_active],
                [
                    'is_active' => $isActive,
                    'activation_root_id' => (int) $activationRoot->getKey(),
                    'affected_categories' => $affectedCategories,
                ],
                $reason,
            );
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createBrand(User $actor, array $attributes, string $reason): Brand
    {
        $attributes['slug'] = $attributes['slug'] ?: Str::slug($attributes['name']);
        $brand = Brand::query()->create($attributes);
        $this->auditLogs->record($actor, 'brand.created', $brand, null, $brand->getAttributes(), $reason);

        return $brand;
    }

    /** @param array<string, mixed> $attributes */
    public function updateBrand(User $actor, Brand $brand, array $attributes, string $reason): Brand
    {
        return $this->update($actor, $brand, $attributes, $reason, 'brand.updated');
    }

    /** @param array<string, mixed> $attributes */
    public function createSetting(User $actor, array $attributes, string $reason): MarketplaceSetting
    {
        $attributes['group'] = self::settingDefinitions()[$attributes['key']]['group'];
        $attributes['updated_by'] = $actor->id;
        $setting = MarketplaceSetting::query()->create($attributes);
        $this->auditLogs->record($actor, 'setting.created', $setting, null, $setting->getAttributes(), $reason);

        return $setting;
    }

    /** @param array<string, mixed> $attributes */
    public function updateSetting(User $actor, MarketplaceSetting $setting, array $attributes, string $reason): MarketplaceSetting
    {
        $attributes['group'] = self::settingDefinitions()[$setting->key]['group'];
        $attributes['updated_by'] = $actor->id;

        return $this->update($actor, $setting, $attributes, $reason, 'setting.updated');
    }

    public function archive(User $actor, Category|Brand|MarketplaceSetting|GoogleProductTaxonomyVersion $model, string $reason): void
    {
        $before = $model->getAttributes();
        $model->delete();
        $this->auditLogs->record($actor, class_basename($model).'.archived', $model, $before, $model->getAttributes(), $reason);
    }

    public function restore(User $actor, Category|Brand|MarketplaceSetting|GoogleProductTaxonomyVersion $model, string $reason): void
    {
        $before = $model->getAttributes();
        $model->restore();
        $this->auditLogs->record($actor, class_basename($model).'.restored', $model, $before, $model->getAttributes(), $reason);
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @param  array<string, mixed>  $attributes
     * @return T
     */
    private function update(User $actor, Model $model, array $attributes, string $reason, string $action): Model
    {
        return DB::transaction(function () use ($actor, $model, $attributes, $reason, $action): Model {
            $before = $model->getAttributes();
            $model->fill($attributes)->save();
            $this->auditLogs->record($actor, $action, $model, $before, $model->getAttributes(), $reason);

            return $model;
        });
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     */
    private function replaceCategoryArtwork(
        User $actor,
        Category $category,
        UploadedFile $image,
        array $crop,
        string $reason,
        string $type,
    ): Category {
        $pathAttribute = $type === 'banner' ? 'banner_image_path' : 'image_path';
        $diskAttribute = $type === 'banner' ? 'banner_image_disk' : 'image_disk';
        $auditAction = $type === 'banner' ? 'category.banner_image_updated' : 'category.image_updated';
        $oldPath = $category->getAttribute($pathAttribute);
        $oldDisk = $category->getAttribute($diskAttribute);
        $stored = $this->artwork->store($category, $image, $crop, $type);

        try {
            $category = DB::transaction(function () use ($actor, $category, $pathAttribute, $diskAttribute, $auditAction, $stored, $reason): Category {
                $before = $category->getAttributes();
                $category->forceFill([
                    $pathAttribute => $stored['path'],
                    $diskAttribute => $stored['disk'],
                ]);
                $this->catalog->saveCategory($category);
                $this->auditLogs->record($actor, $auditAction, $category, $before, $category->getAttributes(), $reason);

                return $category;
            });
        } catch (Throwable $exception) {
            $this->artwork->delete($stored['disk'], $stored['path']);

            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== $stored['path']) {
            $this->artwork->delete(is_string($oldDisk) ? $oldDisk : null, $oldPath);
        }

        return $category;
    }

    private function removeCategoryArtwork(User $actor, Category $category, string $reason, string $type): Category
    {
        $pathAttribute = $type === 'banner' ? 'banner_image_path' : 'image_path';
        $diskAttribute = $type === 'banner' ? 'banner_image_disk' : 'image_disk';
        $auditAction = $type === 'banner' ? 'category.banner_image_removed' : 'category.image_removed';
        $oldPath = $category->getAttribute($pathAttribute);
        $oldDisk = $category->getAttribute($diskAttribute);

        $category = DB::transaction(function () use ($actor, $category, $pathAttribute, $diskAttribute, $auditAction, $reason): Category {
            $before = $category->getAttributes();
            $category->forceFill([
                $pathAttribute => null,
                $diskAttribute => null,
            ]);
            $this->catalog->saveCategory($category);
            $this->auditLogs->record($actor, $auditAction, $category, $before, $category->getAttributes(), $reason);

            return $category;
        });

        if (is_string($oldPath)) {
            $this->artwork->delete(is_string($oldDisk) ? $oldDisk : null, $oldPath);
        }

        return $category;
    }
}
