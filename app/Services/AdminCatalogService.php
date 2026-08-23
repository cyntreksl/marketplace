<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminCatalogService
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

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

    public function createCategory(User $actor, array $attributes, string $reason): Category
    {
        $attributes['slug'] = $attributes['slug'] ?: Str::slug($attributes['name']);
        $category = Category::query()->create($attributes);
        $this->auditLogs->record($actor, 'category.created', $category, null, $category->getAttributes(), $reason);

        return $category;
    }

    public function updateCategory(User $actor, Category $category, array $attributes, string $reason): Category
    {
        return $this->update($actor, $category, $attributes, $reason, 'category.updated');
    }

    public function createBrand(User $actor, array $attributes, string $reason): Brand
    {
        $attributes['slug'] = $attributes['slug'] ?: Str::slug($attributes['name']);
        $brand = Brand::query()->create($attributes);
        $this->auditLogs->record($actor, 'brand.created', $brand, null, $brand->getAttributes(), $reason);

        return $brand;
    }

    public function updateBrand(User $actor, Brand $brand, array $attributes, string $reason): Brand
    {
        return $this->update($actor, $brand, $attributes, $reason, 'brand.updated');
    }

    public function createSetting(User $actor, array $attributes, string $reason): MarketplaceSetting
    {
        $attributes['group'] = self::settingDefinitions()[$attributes['key']]['group'];
        $attributes['updated_by'] = $actor->id;
        $setting = MarketplaceSetting::query()->create($attributes);
        $this->auditLogs->record($actor, 'setting.created', $setting, null, $setting->getAttributes(), $reason);

        return $setting;
    }

    public function updateSetting(User $actor, MarketplaceSetting $setting, array $attributes, string $reason): MarketplaceSetting
    {
        $attributes['group'] = self::settingDefinitions()[$setting->key]['group'];
        $attributes['updated_by'] = $actor->id;

        return $this->update($actor, $setting, $attributes, $reason, 'setting.updated');
    }

    public function archive(User $actor, Model $model, string $reason): void
    {
        $before = $model->getAttributes();
        $model->delete();
        $this->auditLogs->record($actor, class_basename($model).'.archived', $model, $before, $model->getAttributes(), $reason);
    }

    public function restore(User $actor, Model $model, string $reason): void
    {
        $before = $model->getAttributes();
        $model->restore();
        $this->auditLogs->record($actor, class_basename($model).'.restored', $model, $before, $model->getAttributes(), $reason);
    }

    /** @template T of Model @param T $model @return T */
    private function update(User $actor, Model $model, array $attributes, string $reason, string $action): Model
    {
        return DB::transaction(function () use ($actor, $model, $attributes, $reason, $action): Model {
            $before = $model->getAttributes();
            $model->fill($attributes)->save();
            $this->auditLogs->record($actor, $action, $model, $before, $model->getAttributes(), $reason);

            return $model;
        });
    }
}
