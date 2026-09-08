<?php

namespace App\Repositories;

use App\Contracts\Repositories\MarketplaceSettingRepository;
use App\Models\MarketplaceSetting;

class EloquentMarketplaceSettingRepository implements MarketplaceSettingRepository
{
    public function value(string $key): mixed
    {
        return MarketplaceSetting::query()->where('key', $key)->value('value');
    }

    /** @param list<string> $keys */
    public function values(array $keys): array
    {
        return MarketplaceSetting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->map(fn (mixed $value): mixed => is_array($value) ? ($value['value'] ?? $value) : $value)
            ->all();
    }

    public function update(string $key, mixed $value, int $actorId): MarketplaceSetting
    {
        return MarketplaceSetting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => 'auction', 'value' => $value, 'updated_by' => $actorId],
        );
    }
}
