<?php

namespace App\Services;

use App\Contracts\Repositories\MarketplaceSettingRepository;

class MarketplaceSettingsService
{
    public function __construct(private readonly MarketplaceSettingRepository $settings) {}

    public function integer(string $key, int $default): int
    {
        $value = $this->settings->value($key);
        $value = is_array($value) ? ($value['value'] ?? null) : $value;

        return is_numeric($value) ? (int) $value : $default;
    }
}
