<?php

namespace App\Repositories;

use App\Contracts\Repositories\CatalogRepository;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GoogleProductTaxonomyNode;
use App\Models\MarketplaceSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'is_active' => true,
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
            ->update(['is_active' => false, 'is_selectable' => false]);
    }

    public function selectableCategory(int $id): Category
    {
        $category = Category::query()
            ->whereKey($id)
            ->where('is_active', true)
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
            ->select(['id', 'name', 'slug', 'sort_order'])
            ->with(['children' => fn (HasMany $query): HasMany => $query
                ->select(['id', 'parent_id', 'name', 'slug', 'sort_order'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function lookupCategories(?string $search, ?int $parentId): Collection
    {
        $query = Category::query()
            ->where('is_active', true)
            ->withCount(['children as active_children_count' => fn (Builder $query): Builder => $query->where('is_active', true)]);

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
            $query->where('parent_id', $parentId)->limit(100);
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->get();

        return $this->decorateCategoryPaths($categories);
    }

    public function categoryOption(Category $category): array
    {
        $category->loadCount(['children as active_children_count' => fn (Builder $query): Builder => $query->where('is_active', true)]);
        $this->decorateCategoryPaths(collect([$category]));

        return [
            'id' => (int) $category->getKey(),
            'name' => $category->name,
            'path' => (string) ($category->getAttribute('taxonomy_path') ?: $category->name),
            'slug' => $category->slug,
            'is_selectable' => $category->is_selectable,
            'has_children' => (int) $category->getAttribute('active_children_count') > 0,
            'commission_percentage' => $category->commission_percentage,
        ];
    }

    public function activeCategoryOptionBySlug(string $slug): ?array
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();

        return $category === null ? null : $this->categoryOption($category);
    }

    public function activeDescendantIdsForSlug(string $slug): array
    {
        $category = Category::query()->where('slug', $slug)->where('is_active', true)->first();
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
            ->where('is_active', true)
            ->whereIn('google_product_category_id', $googleIds)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();
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
