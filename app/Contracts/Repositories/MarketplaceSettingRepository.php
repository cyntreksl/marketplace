<?php

namespace App\Contracts\Repositories;

use App\Models\MarketplaceSetting;

interface MarketplaceSettingRepository
{
    public function value(string $key): mixed;

    /** @param list<string> $keys
     * @return array<string, mixed>
     */
    public function values(array $keys): array;

    public function update(string $key, mixed $value, string $group, int $actorId): MarketplaceSetting;
}
