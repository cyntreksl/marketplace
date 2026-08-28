<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
    ) {}

    public function home(): Response
    {
        return Inertia::render('storefront/home', [
            'featuredListings' => $this->listings->paginatePublic([], 6)->through(fn (Listing $listing) => $this->listingData($listing)),
            'categories' => $this->catalog->activeTopLevelCategories()->map->only(['id', 'name', 'slug']),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'category', 'brand', 'condition', 'listing_type', 'location', 'min_price', 'max_price']);

        return Inertia::render('storefront/listings/index', [
            'filters' => $filters,
            'listings' => $this->listings->paginatePublic($filters)->through(fn (Listing $listing) => $this->listingData($listing)),
            'categories' => $this->catalog->activeTopLevelCategories()->map->only(['id', 'name', 'slug']),
            'selectedCategory' => isset($filters['category']) ? $this->catalog->activeCategoryOptionBySlug($filters['category']) : null,
        ]);
    }

    public function show(string $listing): Response
    {
        return Inertia::render('storefront/listings/show', [
            'listing' => $this->listingData($this->listings->findPublicBySlug($listing), detailed: true),
            'categories' => $this->catalog->activeTopLevelCategories()->map->only(['id', 'name', 'slug']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listingData(Listing $listing, bool $detailed = false): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'description' => $detailed ? $listing->description : null,
            'condition' => $listing->condition,
            'listingType' => $listing->listing_type,
            'price' => $listing->sale_price ?? $listing->price,
            'location' => $listing->location,
            'warranty' => $listing->warranty,
            'stockQuantity' => $listing->stock_quantity - $listing->reserved_quantity,
            'category' => $listing->category?->only(['name', 'slug']),
            'brand' => $listing->brand?->only(['name', 'slug']),
            'media' => $listing->media->map(fn ($media) => ['path' => $media->path, 'type' => $media->type]),
            'seller' => $listing->sellerProfile?->only(['store_name', 'slug']),
            'auction' => $listing->auction === null ? null : [
                'id' => $listing->auction->id,
                'status' => $listing->auction->status,
                'currentPrice' => $listing->auction->current_price,
                'minimumIncrement' => $listing->auction->minimum_increment,
                'endsAt' => $listing->auction->ends_at->toIso8601String(),
                'bidCount' => $detailed ? $listing->auction->bids->count() : null,
            ],
        ];
    }
}
