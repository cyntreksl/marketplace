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

    public function activeFlashSale(): ?Promotion
    {
        return Promotion::query()
            ->where('placement', 'flash_sale')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where('ends_at', '>=', now())
            ->with(['listings' => fn ($query) => $query
                ->publiclyVisible()
                ->withAvg('reviews as rating_average', 'rating')
                ->withCount('reviews')
                ->with(['brand:id,name,slug', 'category:id,name,slug', 'media', 'sellerProfile:id,store_name,slug', 'auction'])])
            ->orderBy('sort_order')
            ->first();
    }

    public function paginateForAdmin(): LengthAwarePaginator
    {
        return Promotion::query()->with('listings:id,title')->orderBy('placement')->orderBy('sort_order')->paginate(20);
    }

    public function save(Promotion $promotion): Promotion
    {
        $promotion->save();

        return $promotion;
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    public function forMediaMigration(): LazyCollection
    {
        return Promotion::query()
            ->whereNotNull('image_path')
            ->lazyById();
    }
}
