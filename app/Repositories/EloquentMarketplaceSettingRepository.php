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
}
