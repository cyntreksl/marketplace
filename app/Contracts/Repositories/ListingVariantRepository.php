<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use Illuminate\Support\Collection;

interface ListingVariantRepository
{
    /**
     * @param  array<int, array{name: string, values: array<int, string>}>  $options
     * @param  array<int, array{combination_key: string, sku: string|null, gtin: string|null, mpn: string|null, selling_price: mixed, market_price: mixed, wholesale_price: mixed, wholesale_min_quantity: int|null, wholesale_tiers: list<array{minimum_quantity: int, unit_price: mixed}>, stock_quantity: int, is_active: bool, selections: array<int, string>}>  $variants
     * @return Collection<int, ListingVariant>
     */
    public function replaceForListing(Listing $listing, array $options, array $variants): Collection;

    /**
     * @param  array<int, string>  $retainedCombinationKeys
     * @return Collection<int, ListingMedia>
     */
    public function imagesExcept(Listing $listing, array $retainedCombinationKeys): Collection;

    public function deleteForListing(Listing $listing): void;
}
