<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\WholesalePriceTier;

interface WholesalePriceTierRepository
{
    /** @param list<array{minimum_quantity: int, unit_price: mixed}> $tiers */
    public function replaceForListing(Listing $listing, array $tiers): void;

    /** @param list<array{minimum_quantity: int, unit_price: mixed}> $tiers */
    public function replaceForVariant(ListingVariant $variant, array $tiers): void;

    public function lowestForListing(Listing $listing): ?WholesalePriceTier;
}
