<?php

namespace App\Repositories;

use App\Contracts\Repositories\SeoGrowthContentRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentSeoGrowthContentRepository implements SeoGrowthContentRepository
{
    public function categoryByName(string $name): ?Category
    {
        return Category::query()->where('name', $name)->first();
    }

    public function saveCategory(Category $category): void
    {
        $category->save();
    }

    public function brandByName(string $name): ?Brand
    {
        return Brand::query()->where('name', $name)->first();
    }

    public function saveBrand(Brand $brand): void
    {
        $brand->save();
    }

    public function publicStoresWithoutAbout(): Collection
    {
        return SellerProfile::query()
            ->whereIn('status', ['approved', 'active'])
            ->where(fn ($query) => $query->whereNull('about')->orWhere('about', ''))
            ->whereHas('listings', function (Builder $query): void {
                /** @var Builder<Listing> $query */
                $query->retailVisible();
            })
            ->get();
    }

    public function saveSeller(SellerProfile $seller): void
    {
        $seller->save();
    }

    public function listingBySlug(string $slug): ?Listing
    {
        return Listing::query()->where('slug', $slug)->first();
    }

    public function saveListing(Listing $listing): void
    {
        $listing->save();
    }
}
