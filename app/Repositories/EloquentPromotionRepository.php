<?php

namespace App\Repositories;

use App\Contracts\Repositories\PromotionRepository;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class EloquentPromotionRepository implements PromotionRepository
{
    public function activeForPlacement(string $placement, int $limit): Collection
    {
        return Promotion::query()
            ->where('placement', $placement)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function paginateForAdmin(): LengthAwarePaginator
    {
        return Promotion::query()->orderBy('placement')->orderBy('sort_order')->paginate(20);
    }

    public function save(Promotion $promotion): Promotion
    {
        $promotion->save();

        return $promotion;
    }

    public function forMediaMigration(): LazyCollection
    {
        return Promotion::query()
            ->whereNotNull('image_path')
            ->lazyById();
    }
}
