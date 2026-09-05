<?php

namespace App\Contracts\Repositories;

interface MarketplaceSettingRepository
{
    public function value(string $key): mixed;
}
