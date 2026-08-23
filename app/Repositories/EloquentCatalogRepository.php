<?php

namespace App\Repositories;

use App\Contracts\Repositories\CatalogRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCatalogRepository implements CatalogRepository
{
    public function categories(array $filters = []): LengthAwarePaginator
    {
        return Category::query()->with(['parent:id,name'])->withTrashed()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(($filters['archived'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['archived'] ?? null) === 'without', fn ($query) => $query->withoutTrashed())
            ->latest()->paginate(20)->withQueryString();
    }

    public function brands(array $filters = []): LengthAwarePaginator
    {
        return Brand::query()->withTrashed()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(($filters['archived'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['archived'] ?? null) === 'without', fn ($query) => $query->withoutTrashed())
            ->latest()->paginate(20)->withQueryString();
    }

    public function settings(array $filters = []): LengthAwarePaginator
    {
        return MarketplaceSetting::query()->withTrashed()->orderBy('group')->orderBy('key')->paginate(30)->withQueryString();
    }

    public function categoryWithTrashed(int $id): Category
    {
        return Category::withTrashed()->findOrFail($id);
    }

    public function brandWithTrashed(int $id): Brand
    {
        return Brand::withTrashed()->findOrFail($id);
    }

    public function settingWithTrashed(int $id): MarketplaceSetting
    {
        return MarketplaceSetting::withTrashed()->findOrFail($id);
    }
}
