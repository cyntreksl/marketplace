<?php

namespace App\Services;

use App\Models\MarketplaceSetting;

class MarketplaceSettingsService
{
    public function integer(string $key, int $default): int
    {
        return (int) data_get(MarketplaceSetting::query()->where('key', $key)->value('value'), 'value', $default);
    }
}
