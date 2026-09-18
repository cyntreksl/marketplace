<?php

namespace App\Repositories;

use App\Contracts\Repositories\CollectionRepository;
use App\Models\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class EloquentCollectionRepository implements CollectionRepository
{
    public function activeBySlug(string $slug): Collection
    {
        return Collection::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
    }

    public function navigationCollections(): SupportCollection
    {
        return Collection::query()
            ->where('is_active', true)
            ->where('show_in_navigation', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function homepageTileCollections(): SupportCollection
    {
        return Collection::query()
            ->where('is_active', true)
            ->where('show_on_homepage_tile', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function homepageGridCollections(): SupportCollection
    {
        return Collection::query()
            ->where('is_active', true)
            ->where('show_on_homepage_grid', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function paginateForAdmin(): LengthAwarePaginator
    {
        return Collection::withTrashed()->withCount('listings')->orderBy('type')->orderBy('sort_order')->paginate(20);
    }

    public function save(Collection $collection): Collection
    {
        $collection->save();

        return $collection;
    }

    public function delete(Collection $collection): void
    {
        $collection->delete();
    }

    public function restore(Collection $collection): void
    {
        $collection->restore();
    }

    public function withTrashed(int $id): Collection
    {
        return Collection::withTrashed()->findOrFail($id);
    }
}
