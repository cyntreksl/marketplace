<?php

namespace App\Repositories;

use App\Contracts\Repositories\CatalogRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GoogleProductTaxonomyNode;
use App\Models\Listing;
use App\Models\MarketplaceSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EloquentCatalogRepository implements CatalogRepository
{
    /**
     * @param  array{search?: string|null, archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function categories(array $filters = []): LengthAwarePaginator
    {
        return Category::query()->with(['parent:id,name'])->withTrashed()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(($filters['archived'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['archived'] ?? null) === 'without', fn ($query) => $query->withoutTrashed())
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Category $category): Category {
                $category->setAttribute('image_url', $category->imageUrl());
                $category->setAttribute('is_storefront_available', $category->isStorefrontAvailable());

                return $category;
            });
    }

    /**
     * @param  array{search?: string|null, archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function brands(array $filters = []): LengthAwarePaginator
    {
        return Brand::query()->withTrashed()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(($filters['archived'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['archived'] ?? null) === 'without', fn ($query) => $query->withoutTrashed())
            ->orderByRaw('homepage_order is null')->orderBy('homepage_order')->latest()->paginate(20)->withQueryString()
            ->through(function (Brand $brand): Brand {
                $brand->setAttribute('logo_url', $brand->logoUrl());

                return $brand;
            });
    }

    /**
     * @param  array{search?: string|null, archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, MarketplaceSetting>
     */
    public function settings(array $filters = []): LengthAwarePaginator
    {
        return MarketplaceSetting::query()->withTrashed()->orderBy('group')->orderBy('key')->paginate(30)->withQueryString();
    }

    public function categoryWithTrashed(int $id): Category
    {
        return Category::withTrashed()->findOrFail($id);
    }

    public function adminCategoryCount(): int
    {
        return Category::withTrashed()->count();
    }

    /** @return Collection<int, Category> */
    public function adminCategoryChildren(?int $parentId): Collection
    {
        $categories = $this->adminCategoryQuery()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->decorateAdminCategoryPaths($categories);
    }

    /** @return Collection<int, Category> */
    public function searchAdminCategories(
        ?string $search,
        string $status,
        bool $parentOptions = false,
        ?Category $excludedSubtree = null,
        int $limit = 50,
    ): Collection {
        $query = $this->adminCategoryQuery();

        if ($search !== null && $search !== '') {
            $matchingGoogleIds = GoogleProductTaxonomyNode::query()
                ->whereHas('taxonomyVersion', fn (Builder $query): Builder => $query->where('is_active', true))
                ->where('full_path', 'like', '%'.$search.'%')
                ->select('google_product_category_id');

            $query->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')
                ->orWhereIn('google_product_category_id', $matchingGoogleIds));
        }

        match ($status) {
            'storefront_visible' => Category::constrainStorefrontAvailability($query),
            'admin_active' => $query->whereNull('deleted_at')->where('is_active', true),
            'admin_inactive' => $query->whereNull('deleted_at')->where('is_active', false),
            'taxonomy_unavailable' => $query->whereNull('deleted_at')->where('is_taxonomy_available', false),
            'archived' => $query->onlyTrashed(),
            default => null,
        };

        if ($parentOptions) {
            $query->whereNull('deleted_at');
        }

        if ($excludedSubtree !== null) {
            $query->whereNotIn('id', $this->categorySubtreeIds($excludedSubtree));
        }

        $categories = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $this->decorateAdminCategoryPaths($categories);
    }

    public function adminCategoryContext(Category $category): array
    {
        $trailIds = [(int) $category->getKey()];
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = Category::withTrashed()
                ->select(['id', 'parent_id'])
                ->find($parentId);

            if ($parent === null || in_array((int) $parent->getKey(), $trailIds, true)) {
                break;
            }

            array_unshift($trailIds, (int) $parent->getKey());
            $parentId = $parent->parent_id;
        }

        $trailModels = $this->adminCategoryQuery()
            ->whereKey($trailIds)
            ->get()
            ->keyBy(fn (Category $category): int => (int) $category->getKey());
        $trail = collect($trailIds)
            ->map(function (int $categoryId) use ($trailModels): Category {
                /** @var Category $trailCategory */
                $trailCategory = $trailModels->get($categoryId);

                return $trailCategory;
            });
        $this->decorateAdminCategoryPaths($trail);

        $columns = [];
        $columnParentId = null;

        foreach ($trail as $trailCategory) {
            $columns[] = [
                'parent_id' => $columnParentId,
                'categories' => $this->adminCategoryChildren($columnParentId),
            ];
            $columnParentId = (int) $trailCategory->getKey();
        }

        $children = $this->adminCategoryChildren((int) $category->getKey());
        if ($children->isNotEmpty()) {
            $columns[] = [
                'parent_id' => (int) $category->getKey(),
                'categories' => $children,
            ];
        }

        return [
            'selected' => $trail->last(),
            'trail' => $trail,
            'columns' => $columns,
        ];
    }

    /** @return array<int, int> */
    public function categorySubtreeIds(Category $category): array
    {
        $categoryIds = [(int) $category->getKey()];
        $parentIds = $categoryIds;

        while ($parentIds !== []) {
            $parentIds = Category::withTrashed()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->map(fn (int $id): int => $id)
                ->all();
            $categoryIds = [...$categoryIds, ...$parentIds];
        }

        return array_values(array_unique($categoryIds));
    }

    public function saveCategory(Category $category): Category
    {
        $category->save();

        return $category;
    }

    public function categoryArtworkForMigration(): LazyCollection
    {
        return Category::withTrashed()
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('image_path')
                ->orWhereNotNull('banner_image_path'))
            ->lazyById();
    }

    public function categoryActivationRoot(Category $category): Category
    {
        $activationRoot = $category;
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = Category::query()->whereKey($parentId)->lockForUpdate()->first();

            if ($parent === null) {
                break;
            }

            if (! $parent->is_active) {
                $activationRoot = $parent;
            }

            $parentId = $parent->parent_id;
        }

        return $activationRoot;
    }

    public function setCategorySubtreeActive(Category $category, bool $isActive): int
    {
        return Category::withTrashed()
            ->whereIn('id', $this->categorySubtreeIds($category))
            ->update(['is_active' => $isActive]);
    }

    public function brandWithTrashed(int $id): Brand
    {
        return Brand::withTrashed()->findOrFail($id);
    }

    public function findBrandByNameForUpdate(string $name): ?Brand
    {
        return Brand::withTrashed()
            ->where('name', $name)
            ->lockForUpdate()
            ->first();
    }

    public function saveBrand(Brand $brand): Brand
    {
        $brand->save();

        return $brand;
    }

    public function settingWithTrashed(int $id): MarketplaceSetting
    {
        return MarketplaceSetting::withTrashed()->findOrFail($id);
    }

    public function saveMappedCategory(
        int $googleProductCategoryId,
        ?int $parentId,
        string $name,
        string $fullPath,
        bool $isSelectable,
        int $sortOrder,
    ): Category {
        $category = Category::withTrashed()
            ->where('google_product_category_id', $googleProductCategoryId)
            ->lockForUpdate()
            ->first();

        if ($category === null) {
            $category = new Category([
                'google_product_category_id' => $googleProductCategoryId,
                'slug' => $this->uniqueCategorySlug($fullPath, $googleProductCategoryId),
                'commission_percentage' => 8,
                'return_window_days' => 7,
                'cod_enabled' => true,
            ]);
        }

        if ($category->trashed()) {
            $category->restore();
        }

        $category->forceFill([
            'parent_id' => $parentId,
            'name' => $name,
            'is_taxonomy_available' => true,
            'is_selectable' => $isSelectable,
            'sort_order' => $sortOrder,
        ])->save();

        return $category;
    }

    public function deactivateMappedCategoriesExcept(array $permittedGoogleProductCategoryIds): void
    {
        Category::withTrashed()
            ->whereNotNull('google_product_category_id')
            ->whereNotIn('google_product_category_id', $permittedGoogleProductCategoryIds)
            ->update(['is_taxonomy_available' => false, 'is_selectable' => false]);
    }

    public function selectableCategory(int $id): Category
    {
        $category = Category::query()
            ->whereKey($id)
            ->storefrontAvailable()
            ->where('is_selectable', true)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'Choose an active leaf category for this listing.',
            ]);
        }

        return $category;
    }

    public function activeTopLevelCategories(): Collection
    {
        return Category::query()
            ->select(['id', 'name', 'slug', 'image_path', 'image_disk', 'sort_order'])
            ->with(['children' => fn ($query) => $query
                ->select(['id', 'parent_id', 'name', 'slug', 'image_path', 'image_disk', 'sort_order'])
                ->storefrontAvailable()
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->whereNull('parent_id')
            ->storefrontAvailable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function publicBrands(): Collection
    {
        return Brand::query()
            ->withCount(['listings' => fn ($query) => $query->publiclyVisible()])
            ->orderBy('name')
            ->get()
            ->map(function (Brand $brand): Brand {
                $brand->setAttribute('logo_url', $brand->logoUrl());

                return $brand;
            });
    }

    public function activeCategoryBySlug(string $slug): Category
    {
        return Category::query()->storefrontAvailable()->where('slug', $slug)->firstOrFail();
    }

    public function activeBrandBySlug(string $slug): Brand
    {
        return Brand::query()->where('slug', $slug)->firstOrFail();
    }

    public function sitemapCategories(): Collection
    {
        return Category::query()
            ->select(['id', 'slug', 'updated_at'])
            ->storefrontAvailable()
            ->orderBy('id')
            ->get();
    }

    public function sitemapBrands(): Collection
    {
        return Brand::query()
            ->select(['id', 'slug', 'updated_at'])
            ->whereIn('id', Listing::query()->select('brand_id')->directlyVisible())
            ->orderBy('id')
            ->get();
    }

    public function topBrands(int $limit = 8): Collection
    {
        return Brand::query()
            ->where('is_featured', true)
            ->orderByRaw('homepage_order is null')
            ->orderBy('homepage_order')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function (Brand $brand): Brand {
                $brand->setAttribute('logo_url', $brand->logoUrl());

                return $brand;
            });
    }

    public function popularHomepageCategories(int $limit = 10): Collection
    {
        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'image_path', 'image_disk', 'parent_id', 'sort_order'])
            ->storefrontAvailable()
            ->where('is_popular', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($categories->isNotEmpty()) {
            return $categories;
        }

        return Category::query()
            ->select(['id', 'name', 'slug', 'image_path', 'image_disk', 'parent_id', 'sort_order'])
            ->whereNull('parent_id')
            ->storefrontAvailable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function featuredHomepageCategories(): Collection
    {
        return Category::query()
            ->select(['id', 'name', 'slug', 'image_path', 'image_disk', 'banner_image_path', 'banner_image_disk', 'homepage_order'])
            ->storefrontAvailable()
            ->whereNotNull('homepage_order')
            ->orderBy('homepage_order')
            ->limit(5)
            ->get();
    }

    public function selectedHomepageCategories(): Collection
    {
        return Category::query()
            ->select(['id', 'name', 'slug', 'image_path', 'image_disk', 'banner_image_path', 'banner_image_disk', 'is_popular', 'homepage_order'])
            ->storefrontAvailable()
            ->where(fn (Builder $query): Builder => $query
                ->where('is_popular', true)
                ->orWhereNotNull('homepage_order'))
            ->orderByRaw('homepage_order is null')
            ->orderBy('homepage_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category): Category {
                $category->setAttribute('image_url', $category->imageUrl());

                return $category;
            });
    }

    public function replaceHomepageCategories(array $popularCategoryIds, array $featuredCategoryIds): void
    {
        Category::query()->where('is_popular', true)->update(['is_popular' => false]);
        Category::query()->whereNotNull('homepage_order')->update(['homepage_order' => null]);

        if ($popularCategoryIds !== []) {
            Category::query()->whereIn('id', $popularCategoryIds)->update(['is_popular' => true]);
        }

        foreach (array_values($featuredCategoryIds) as $index => $categoryId) {
            Category::query()->whereKey($categoryId)->update(['homepage_order' => $index + 1]);
        }
    }

    public function availableBrands(): Collection
    {
        return Brand::query()
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get();
    }

    public function lookupCategories(?string $search, ?int $parentId, bool $leafOnly = false): Collection
    {
        $query = Category::query()
            ->storefrontAvailable()
            ->withCount(['children as active_children_count' => fn (Builder $query): Builder => $query
                ->whereNull('categories.deleted_at')
                ->where('is_active', true)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('is_taxonomy_available')
                    ->orWhere('is_taxonomy_available', true))]);

        if ($leafOnly) {
            $query->where('is_selectable', true);
        }

        if ($search !== null && $search !== '') {
            $matchingGoogleIds = GoogleProductTaxonomyNode::query()
                ->whereHas('taxonomyVersion', fn (Builder $query): Builder => $query->where('is_active', true))
                ->where('full_path', 'like', '%'.$search.'%')
                ->select('google_product_category_id');

            $query->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhereIn('google_product_category_id', $matchingGoogleIds))
                ->limit(30);
        } else {
            if (! $leafOnly || $parentId !== null) {
                $query->where('parent_id', $parentId);
            }

            $query->limit(100);
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->get();

        return $this->decorateCategoryPaths($categories);
    }

    public function categoryOption(Category $category): array
    {
        $category->loadCount(['children as active_children_count' => fn (Builder $query): Builder => $query
            ->whereNull('categories.deleted_at')
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('is_taxonomy_available')
                ->orWhere('is_taxonomy_available', true))]);
        $this->decorateCategoryPaths(collect([$category]));

        return [
            'id' => (int) $category->getKey(),
            'name' => $category->name,
            'path' => (string) ($category->getAttribute('taxonomy_path') ?: $category->name),
            'slug' => $category->slug,
            'is_selectable' => $category->is_selectable,
            'has_children' => (int) $category->getAttribute('active_children_count') > 0,
            'commission_percentage' => (string) $category->commission_percentage,
        ];
    }

    public function activeCategoryOptionBySlug(string $slug): ?array
    {
        $category = Category::query()->where('slug', $slug)->storefrontAvailable()->first();

        return $category === null ? null : $this->categoryOption($category);
    }

    public function activeDescendantIdsForSlug(string $slug): array
    {
        $category = Category::query()->where('slug', $slug)->storefrontAvailable()->first();
        if ($category === null) {
            return [];
        }

        if ($category->google_product_category_id === null) {
            return [(int) $category->getKey()];
        }

        $node = GoogleProductTaxonomyNode::query()
            ->where('google_product_category_id', $category->google_product_category_id)
            ->whereHas('taxonomyVersion', fn (Builder $query): Builder => $query->where('is_active', true))
            ->first();

        if ($node === null) {
            return [(int) $category->getKey()];
        }

        $googleIds = GoogleProductTaxonomyNode::query()
            ->where('google_product_taxonomy_version_id', $node->google_product_taxonomy_version_id)
            ->where(fn (Builder $query): Builder => $query
                ->where('full_path', $node->full_path)
                ->orWhere('full_path', 'like', $node->full_path.' > %'))
            ->select('google_product_category_id');

        return Category::query()
            ->storefrontAvailable()
            ->whereIn('google_product_category_id', $googleIds)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();
    }

    public function activeCategoryContextBySlug(string $slug): ?array
    {
        $category = Category::query()
            ->select(['id', 'parent_id', 'name', 'slug', 'image_path', 'image_disk'])
            ->where('slug', $slug)
            ->storefrontAvailable()
            ->first();

        if ($category === null) {
            return null;
        }

        $children = Category::query()
            ->select(['id', 'parent_id', 'name', 'slug', 'image_path', 'image_disk', 'sort_order'])
            ->where('parent_id', $category->id)
            ->storefrontAvailable()
            ->withCount(['children as active_children_count' => fn (Builder $query): Builder => $query
                ->whereNull('categories.deleted_at')
                ->where('is_active', true)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('is_taxonomy_available')
                    ->orWhere('is_taxonomy_available', true))])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $child): array => [
                ...$this->categoryNavigationData($child),
                'has_children' => (int) $child->getAttribute('active_children_count') > 0,
            ])
            ->values()
            ->all();

        return [
            'current' => $this->categoryNavigationData($category),
            'ancestors' => $this->activeAncestors($category),
            'children' => $children,
        ];
    }

    public function activeCategoryTrailBySlug(string $slug): array
    {
        $context = $this->activeCategoryContextBySlug($slug);

        if ($context === null) {
            return [];
        }

        return [...$context['ancestors'], $context['current']];
    }

    /** @return Builder<Category> */
    private function adminCategoryQuery(): Builder
    {
        return Category::withTrashed()
            ->withCount(['children as all_children_count' => fn (Builder $query): Builder => $query
                ->withoutGlobalScope(SoftDeletingScope::class)]);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function decorateAdminCategoryPaths(Collection $categories): Collection
    {
        $categoryMap = $categories->keyBy(fn (Category $category): int => (int) $category->getKey());
        $parentIds = $categories->pluck('parent_id')
            ->filter()
            ->map(fn (int $id): int => $id)
            ->unique()
            ->reject(fn (int $id): bool => $categoryMap->has($id))
            ->values();

        while ($parentIds->isNotEmpty()) {
            $parents = Category::withTrashed()
                ->select(['id', 'parent_id', 'name'])
                ->whereKey($parentIds)
                ->get();

            if ($parents->isEmpty()) {
                break;
            }

            foreach ($parents as $parent) {
                $categoryMap->put((int) $parent->getKey(), $parent);
            }

            $parentIds = $parents->pluck('parent_id')
                ->filter()
                ->map(fn (int $id): int => $id)
                ->unique()
                ->reject(fn (int $id): bool => $categoryMap->has($id))
                ->values();
        }

        return $categories->each(function (Category $category) use ($categoryMap): void {
            $path = [$category->name];
            $parentId = $category->parent_id;
            $visitedIds = [(int) $category->getKey()];

            while ($parentId !== null && ! in_array($parentId, $visitedIds, true)) {
                $parent = $categoryMap->get($parentId);
                if (! $parent instanceof Category) {
                    break;
                }

                array_unshift($path, $parent->name);
                $visitedIds[] = (int) $parent->getKey();
                $parentId = $parent->parent_id;
            }

            $category->setAttribute('category_path', implode(' > ', $path));
        });
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function decorateCategoryPaths(Collection $categories): Collection
    {
        $googleIds = $categories->pluck('google_product_category_id')->filter()->values();
        if ($googleIds->isEmpty()) {
            return $categories;
        }

        $paths = GoogleProductTaxonomyNode::query()
            ->whereIn('google_product_category_id', $googleIds)
            ->whereHas('taxonomyVersion', fn (Builder $query): Builder => $query->where('is_active', true))
            ->pluck('full_path', 'google_product_category_id');

        return $categories->each(function (Category $category) use ($paths): void {
            $path = $paths->get($category->google_product_category_id);
            if ($path === null) {
                return;
            }

            $parts = explode(' > ', $path);
            $parts[0] = config('catalog.taxonomy.department_path_names.'.$parts[0], $parts[0]);

            $category->setAttribute('taxonomy_path', implode(' > ', $parts));
        });
    }

    /** @return array<int, array{id: int, name: string, slug: string, image_url: string|null}> */
    private function activeAncestors(Category $category): array
    {
        $ancestors = [];
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = Category::query()
                ->select(['id', 'parent_id', 'name', 'slug', 'image_path', 'image_disk'])
                ->whereKey($parentId)
                ->storefrontAvailable()
                ->first();

            if ($parent === null) {
                break;
            }

            array_unshift($ancestors, $this->categoryNavigationData($parent));
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }

    /** @return array{id: int, name: string, slug: string, image_url: string|null} */
    private function categoryNavigationData(Category $category): array
    {
        return [
            'id' => (int) $category->getKey(),
            'name' => $category->name,
            'slug' => $category->slug,
            'image_url' => $category->imageUrl(),
        ];
    }

    private function uniqueCategorySlug(string $fullPath, int $googleProductCategoryId): string
    {
        $base = Str::slug($fullPath);
        $slug = $base;
        $suffix = 2;

        if (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-gpc-'.$googleProductCategoryId;
        }

        while (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-gpc-'.$googleProductCategoryId.'-'.$suffix++;
        }

        return $slug;
    }
}
