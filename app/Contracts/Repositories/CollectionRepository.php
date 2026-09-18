<?php

namespace App\Contracts\Repositories;

use App\Models\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

interface CollectionRepository
{
    public function activeBySlug(string $slug): Collection;

    /** @return SupportCollection<int, Collection> */
    public function navigationCollections(): SupportCollection;

    /** @return SupportCollection<int, Collection> */
    public function homepageTileCollections(): SupportCollection;

    /** @return SupportCollection<int, Collection> */
    public function homepageGridCollections(): SupportCollection;

    /** @return LengthAwarePaginator<int, Collection> */
    public function paginateForAdmin(): LengthAwarePaginator;

    public function save(Collection $collection): Collection;

    public function delete(Collection $collection): void;

    public function restore(Collection $collection): void;

    public function withTrashed(int $id): Collection;
}
