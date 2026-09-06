<?php

namespace App\Contracts\Repositories;

use App\Models\SellerProfile;
use Illuminate\Support\Collection;

interface SellerStoreRepository
{
    public function findPublic(string $slug): SellerProfile;

    public function findOwned(int $userId): SellerProfile;

    /** @param array<string, mixed> $attributes
     * @return array<int, string>
     */
    public function updateBranding(int $sellerId, array $attributes): array;

    /** @return Collection<int, SellerProfile> */
    public function sitemapStores(): Collection;
}
