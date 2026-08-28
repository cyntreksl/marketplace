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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AdminCatalogService
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly AuditLogService $auditLogs,
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
        $attributes['slug'] = $attributes['slug'] ?: Str::slug($attributes['name']);
        $storedImagePath = null;

        try {
            return DB::transaction(function () use ($actor, $attributes, $image, $reason, &$storedImagePath): Category {
                $category = $this->catalog->saveCategory(new Category($attributes));

                if ($image instanceof UploadedFile) {
                    $storedImagePath = $this->storeCategoryImage($category, $image);
                    $category->forceFill(['image_path' => $storedImagePath]);
                    $this->catalog->saveCategory($category);
                }

                $this->auditLogs->record($actor, 'category.created', $category, null, $category->getAttributes(), $reason);

                return $category;
            });
        } catch (Throwable $exception) {
            if ($storedImagePath !== null) {
                Storage::disk($this->mediaDisk())->delete($storedImagePath);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function updateCategory(User $actor, Category $category, array $attributes, string $reason): Category
    {
        return DB::transaction(function () use ($actor, $category, $attributes, $reason): Category {
            $before = $category->getAttributes();
            $category->fill($attributes);
            $this->catalog->saveCategory($category);
            $this->auditLogs->record($actor, 'category.updated', $category, $before, $category->getAttributes(), $reason);

            return $category;
        });
    }

    public function replaceCategoryImage(User $actor, Category $category, UploadedFile $image, string $reason): Category
    {
        $oldImagePath = $category->image_path;
        $newImagePath = $this->storeCategoryImage($category, $image);

        try {
            $category = DB::transaction(function () use ($actor, $category, $newImagePath, $reason): Category {
                $before = $category->getAttributes();
                $category->forceFill(['image_path' => $newImagePath]);
                $this->catalog->saveCategory($category);
                $this->auditLogs->record($actor, 'category.image_updated', $category, $before, $category->getAttributes(), $reason);

                return $category;
            });
        } catch (Throwable $exception) {
            Storage::disk($this->mediaDisk())->delete($newImagePath);

            throw $exception;
        }

        if ($oldImagePath !== null && $oldImagePath !== $newImagePath) {
            Storage::disk($this->mediaDisk())->delete($oldImagePath);
        }

        return $category;
    }

    public function removeCategoryImage(User $actor, Category $category, string $reason): Category
    {
        $oldImagePath = $category->image_path;

        $category = DB::transaction(function () use ($actor, $category, $reason): Category {
            $before = $category->getAttributes();
            $category->forceFill(['image_path' => null]);
            $this->catalog->saveCategory($category);
            $this->auditLogs->record($actor, 'category.image_removed', $category, $before, $category->getAttributes(), $reason);

            return $category;
        });

        if ($oldImagePath !== null) {
            Storage::disk($this->mediaDisk())->delete($oldImagePath);
        }

        return $category;
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

    private function storeCategoryImage(Category $category, UploadedFile $image): string
    {
        $path = $image->store('categories/'.$category->getKey(), $this->mediaDisk());

        if ($path === false) {
            throw new RuntimeException('The category image could not be stored.');
        }

        return $path;
    }

    private function mediaDisk(): string
    {
        return (string) config('filesystems.media', 'public');
    }
}
