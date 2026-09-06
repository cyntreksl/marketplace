<?php

namespace App\Repositories;

use App\Contracts\Repositories\SellerStoreRepository;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentSellerStoreRepository implements SellerStoreRepository
{
    public function findPublic(string $slug): SellerProfile
    {
        return $this->publicQuery()->where('slug', $slug)->firstOrFail();
    }

    public function findOwned(int $userId): SellerProfile
    {
        return $this->publicQuery()->where('user_id', $userId)->firstOrFail();
    }

    public function updateBranding(int $sellerId, array $attributes): array
    {
        return DB::transaction(function () use ($sellerId, $attributes): array {
            $seller = $this->publicQuery()->lockForUpdate()->findOrFail($sellerId);
            $oldPaths = [];
            foreach (['logo_path', 'cover_path'] as $field) {
                if (array_key_exists($field, $attributes) && $seller->{$field} !== null) {
                    $oldPaths[] = (string) $seller->{$field};
                }
            }
            $seller->fill($attributes)->save();

            return $oldPaths;
        });
    }

    public function sitemapStores(): Collection
    {
        return $this->publicQuery()->whereIn('id', Listing::query()->publiclyVisible()->select('seller_profile_id'))->orderBy('id')->get();
    }

    /** @return Builder<SellerProfile> */
    private function publicQuery(): Builder
    {
        return SellerProfile::query()->whereIn('status', ['approved', 'active'])
            ->withCount(['listings as public_product_count' => fn ($query) => $query->publiclyVisible()]);
    }
}
