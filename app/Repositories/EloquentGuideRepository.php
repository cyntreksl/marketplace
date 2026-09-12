<?php

namespace App\Repositories;

use App\Contracts\Repositories\GuideRepository;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentGuideRepository implements GuideRepository
{
    public function published(int $perPage = 12): LengthAwarePaginator
    {
        return $this->publishedQuery()
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function publishedBySlug(string $slug): Guide
    {
        return $this->publishedQuery()->where('slug', $slug)->firstOrFail();
    }

    public function bySlug(string $slug): ?Guide
    {
        return Guide::withTrashed()->where('slug', $slug)->first();
    }

    public function withTrashed(int $id): Guide
    {
        return Guide::withTrashed()->findOrFail($id);
    }

    public function admin(array $filters): LengthAwarePaginator
    {
        return Guide::query()
            ->withTrashed()
            ->with('categories:id,name,slug')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')))
            ->when(($filters['status'] ?? null) === 'published', fn (Builder $query): Builder => $query->where('status', 'published')->whereNull('deleted_at'))
            ->when(($filters['status'] ?? null) === 'draft', fn (Builder $query): Builder => $query->where('status', 'draft')->whereNull('deleted_at'))
            ->when(($filters['status'] ?? null) === 'archived', fn (Builder $query): Builder => $query->onlyTrashed())
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function categoryOptions(): Collection
    {
        return Category::query()
            ->storefrontAvailable()
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get();
    }

    public function sitemapGuides(): Collection
    {
        return $this->publishedQuery()->select(['id', 'slug', 'updated_at'])->get();
    }

    public function publishedForCategory(int $categoryId, int $limit = 6): Collection
    {
        return $this->publishedQuery()
            ->whereHas('categories', fn (Builder $query): Builder => $query->whereKey($categoryId))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function publishedForBrand(int $brandId, int $limit = 6): Collection
    {
        return $this->publishedQuery()
            ->whereHas('categories.listings', fn (Builder $query): Builder => $query
                ->where('brand_id', $brandId)
                ->retailVisible())
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function relatedListings(array $categoryIds, int $limit = 8): Collection
    {
        if ($categoryIds === []) {
            return collect();
        }

        return Listing::query()
            ->retailVisible()
            ->whereIn('category_id', $categoryIds)
            ->with(['brand:id,name,slug', 'category:id,name,slug', 'media', 'sellerProfile:id,store_name,slug'])
            ->latest('listings.created_at')
            ->limit($limit)
            ->get();
    }

    public function save(Guide $guide): Guide
    {
        $guide->save();

        return $guide;
    }

    public function syncCategories(Guide $guide, array $categoryIds): void
    {
        $guide->categories()->sync(collect($categoryIds)->values()->mapWithKeys(
            fn (int $categoryId, int $index): array => [$categoryId => ['sort_order' => $index]],
        )->all());
    }

    public function delete(Guide $guide): void
    {
        $guide->delete();
    }

    public function restore(Guide $guide): void
    {
        $guide->restore();
    }

    /** @return Builder<Guide> */
    private function publishedQuery(): Builder
    {
        return Guide::query()
            ->with('categories:id,name,slug')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
