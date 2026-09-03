<?php

namespace App\Contracts\Repositories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface CatalogRepository
{
    /**
     * @param  array{search?: string|null, archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function categories(array $filters = []): LengthAwarePaginator;

    /**
     * @param  array{search?: string|null, archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function brands(array $filters = []): LengthAwarePaginator;

    /**
     * @param  array{search?: string|null, archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, MarketplaceSetting>
     */
    public function settings(array $filters = []): LengthAwarePaginator;

    public function categoryWithTrashed(int $id): Category;

    public function adminCategoryCount(): int;

    /** @return Collection<int, Category> */
    public function adminCategoryChildren(?int $parentId): Collection;

    /**
     * @return Collection<int, Category>
     */
    public function searchAdminCategories(
        ?string $search,
        string $status,
        bool $parentOptions = false,
        ?Category $excludedSubtree = null,
        int $limit = 50,
    ): Collection;

    /**
     * @return array{
     *     selected: Category,
     *     trail: Collection<int, Category>,
     *     columns: array<int, array{parent_id: int|null, categories: Collection<int, Category>}>
     * }
     */
    public function adminCategoryContext(Category $category): array;

    /** @return array<int, int> */
    public function categorySubtreeIds(Category $category): array;

    public function saveCategory(Category $category): Category;

    /** @return LazyCollection<int, Category> */
    public function categoryArtworkForMigration(): LazyCollection;

    public function categoryActivationRoot(Category $category): Category;

    public function setCategorySubtreeActive(Category $category, bool $isActive): int;

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

    /** @return Collection<int, Brand> */
    public function publicBrands(): Collection;

    /** @return Collection<int, Brand> */
    public function topBrands(int $limit = 8): Collection;

    /** @return Collection<int, Category> */
    public function popularHomepageCategories(int $limit = 10): Collection;

    /** @return Collection<int, Category> */
    public function featuredHomepageCategories(): Collection;

    /** @return Collection<int, Category> */
    public function selectedHomepageCategories(): Collection;

    /**
     * @param  array<int, int>  $popularCategoryIds
     * @param  array<int, int>  $featuredCategoryIds
     */
    public function replaceHomepageCategories(array $popularCategoryIds, array $featuredCategoryIds): void;

    /** @return Collection<int, Brand> */
    public function availableBrands(): Collection;

    /** @return Collection<int, Category> */
    public function lookupCategories(?string $search, ?int $parentId, bool $leafOnly = false): Collection;

    /** @return array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string} */
    public function categoryOption(Category $category): array;

    /** @return array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}|null */
    public function activeCategoryOptionBySlug(string $slug): ?array;

    /** @return array<int, int> */
    public function activeDescendantIdsForSlug(string $slug): array;

    /**
     * @return array{
     *     current: array{id: int, name: string, slug: string, image_url: string|null},
     *     ancestors: array<int, array{id: int, name: string, slug: string, image_url: string|null}>,
     *     children: array<int, array{id: int, name: string, slug: string, image_url: string|null, has_children: bool}>
     * }|null
     */
    public function activeCategoryContextBySlug(string $slug): ?array;

    /** @return array<int, array{id: int, name: string, slug: string, image_url: string|null}> */
    public function activeCategoryTrailBySlug(string $slug): array;

    public function activeCategoryBySlug(string $slug): Category;

    public function activeBrandBySlug(string $slug): Brand;

    /** @return Collection<int, Category> */
    public function sitemapCategories(): Collection;

    /** @return Collection<int, Brand> */
    public function sitemapBrands(): Collection;
}
