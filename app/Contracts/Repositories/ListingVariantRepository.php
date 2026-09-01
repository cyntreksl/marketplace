<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;

interface ListingVariantRepository
{
    /**
     * @param  array<int, array{name: string, values: array<int, string>}>  $options
     * @param  array<int, array{combination_key: string, sku: string|null, stock_quantity: int, selections: array<int, string>}>  $variants
     */
    public function replaceForListing(Listing $listing, array $options, array $variants): void;

    public function deleteForListing(Listing $listing): void;
}
