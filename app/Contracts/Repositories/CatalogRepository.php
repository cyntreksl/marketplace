<?php

namespace App\Contracts\Repositories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CatalogRepository
{
    public function categories(array $filters = []): LengthAwarePaginator;

    public function brands(array $filters = []): LengthAwarePaginator;

    public function settings(array $filters = []): LengthAwarePaginator;

    public function categoryWithTrashed(int $id): Category;

    public function brandWithTrashed(int $id): Brand;

    public function findBrandByNameForUpdate(string $name): ?Brand;

    public function saveBrand(Brand $brand): Brand;

    public function settingWithTrashed(int $id): MarketplaceSetting;

    public function saveMappedCategory(
        int $googleProductCategoryId,
        ?int $parentId,
        string $name,
        string $fullPath,
        bool $isSelectable,
        int $sortOrder,
    ): Category;

    /** @param array<int, int> $permittedGoogleProductCategoryIds */
    public function deactivateMappedCategoriesExcept(array $permittedGoogleProductCategoryIds): void;

    public function selectableCategory(int $id): Category;

    /** @return Collection<int, Category> */
    public function activeTopLevelCategories(): Collection;

    /** @return Collection<int, Category> */
    public function lookupCategories(?string $search, ?int $parentId): Collection;

    /** @return array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string} */
    public function categoryOption(Category $category): array;

    /** @return array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}|null */
    public function activeCategoryOptionBySlug(string $slug): ?array;

    /** @return array<int, int> */
    public function activeDescendantIdsForSlug(string $slug): array;
}
