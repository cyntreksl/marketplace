<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingVariant;

class ListingPricingService
{
    /** @return array{unitPrice: string, tier: 'retail'|'wholesale', minimumQuantity: int}|null */
    public function forQuantity(Listing $listing, ?ListingVariant $variant, int $quantity): ?array
    {
        $wholesalePrice = $variant === null ? $listing->wholesale_price : $variant->wholesale_price;
        $wholesaleMinimum = $variant === null ? $listing->wholesale_min_quantity : $variant->wholesale_min_quantity;

        if (
            $listing->is_wholesale_enabled
            && $wholesalePrice !== null
            && $wholesaleMinimum !== null
            && $quantity >= $wholesaleMinimum
        ) {
            return [
                'unitPrice' => (string) $wholesalePrice,
                'tier' => 'wholesale',
                'minimumQuantity' => (int) $wholesaleMinimum,
            ];
        }

        if (! $listing->is_retail_enabled) {
            return null;
        }

        $retailPrice = $variant?->buyNowPrice() ?? $listing->buyNowPrice();

        if ($retailPrice === null) {
            return null;
        }

        return [
            'unitPrice' => $retailPrice,
            'tier' => 'retail',
            'minimumQuantity' => 1,
        ];
    }

    public function wholesaleMinimum(Listing $listing, ?ListingVariant $variant): ?int
    {
        $minimum = $variant === null ? $listing->wholesale_min_quantity : $variant->wholesale_min_quantity;

        return $listing->is_wholesale_enabled && $minimum !== null ? (int) $minimum : null;
    }
}
