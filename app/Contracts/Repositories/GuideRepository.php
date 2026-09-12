<?php

namespace App\Contracts\Repositories;

use App\Models\Category;
use App\Models\Guide;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GuideRepository
{
    /** @return LengthAwarePaginator<int, Guide> */
    public function published(int $perPage = 12): LengthAwarePaginator;

    public function publishedBySlug(string $slug): Guide;

    public function bySlug(string $slug): ?Guide;

    public function withTrashed(int $id): Guide;

    /** @param array{search?: string|null, status?: string|null} $filters
     * @return LengthAwarePaginator<int, Guide>
     */
    public function admin(array $filters): LengthAwarePaginator;

    /** @return Collection<int, Category> */
    public function categoryOptions(): Collection;

    /** @return Collection<int, Guide> */
    public function sitemapGuides(): Collection;

    /** @return Collection<int, Guide> */
    public function publishedForCategory(int $categoryId, int $limit = 6): Collection;

    /** @return Collection<int, Guide> */
    public function publishedForBrand(int $brandId, int $limit = 6): Collection;

    /** @param array<int, int> $categoryIds
     * @return Collection<int, Listing>
     */
    public function relatedListings(array $categoryIds, int $limit = 8): Collection;

    public function save(Guide $guide): Guide;

    /** @param array<int, int> $categoryIds */
    public function syncCategories(Guide $guide, array $categoryIds): void;

    public function delete(Guide $guide): void;

    public function restore(Guide $guide): void;
}
