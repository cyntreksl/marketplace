<?php

namespace App\Contracts\Repositories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CatalogRepository
{
    public function categories(array $filters = []): LengthAwarePaginator;

    public function brands(array $filters = []): LengthAwarePaginator;

    public function settings(array $filters = []): LengthAwarePaginator;

    public function categoryWithTrashed(int $id): Category;

    public function brandWithTrashed(int $id): Brand;

    public function settingWithTrashed(int $id): MarketplaceSetting;
}
