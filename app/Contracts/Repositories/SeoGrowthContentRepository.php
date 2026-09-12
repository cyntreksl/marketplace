<?php

namespace App\Contracts\Repositories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Support\Collection;

interface SeoGrowthContentRepository
{
    public function categoryByName(string $name): ?Category;

    public function saveCategory(Category $category): void;

    public function brandByName(string $name): ?Brand;

    public function saveBrand(Brand $brand): void;

    /** @return Collection<int, SellerProfile> */
    public function publicStoresWithoutAbout(): Collection;

    public function saveSeller(SellerProfile $seller): void;

    public function listingBySlug(string $slug): ?Listing;

    public function saveListing(Listing $listing): void;
}
