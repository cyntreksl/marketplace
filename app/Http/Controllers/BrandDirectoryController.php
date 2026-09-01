<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Services\StorefrontService;
use Inertia\Inertia;
use Inertia\Response;

class BrandDirectoryController extends Controller
{
    public function __invoke(CatalogRepository $catalog, StorefrontService $storefront): Response
    {
        return Inertia::render('storefront/brands', [
            'categories' => $storefront->navigationCategories(),
            'brands' => $catalog->publicBrands()->map(fn ($brand): array => [
                ...$brand->only(['id', 'name', 'slug']),
                'logoUrl' => $brand->getAttribute('logo_url'),
                'listingCount' => (int) $brand->getAttribute('listings_count'),
            ])->values(),
        ]);
    }
}
