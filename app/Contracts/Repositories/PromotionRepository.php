<?php

namespace App\Contracts\Repositories;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PromotionRepository
{
    /** @return Collection<int, Promotion> */
    public function activeForPlacement(string $placement, int $limit): Collection;

    /** @return LengthAwarePaginator<int, Promotion> */
    public function paginateForAdmin(): LengthAwarePaginator;

    public function save(Promotion $promotion): Promotion;
}
