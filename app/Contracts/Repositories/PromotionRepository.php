<?php

namespace App\Contracts\Repositories;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface PromotionRepository
{
    /** @return Collection<int, Promotion> */
    public function activeForPlacement(string $placement, int $limit): Collection;

    public function activeFlashSale(): ?Promotion;

    /** @return LengthAwarePaginator<int, Promotion> */
    public function paginateForAdmin(): LengthAwarePaginator;

    public function save(Promotion $promotion): Promotion;

    public function delete(Promotion $promotion): void;

    /** @return LazyCollection<int, Promotion> */
    public function forMediaMigration(): LazyCollection;
}
