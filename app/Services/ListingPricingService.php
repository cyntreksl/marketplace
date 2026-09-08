<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingVariant;

class ListingPricingService
{
    /** @return array{unitPrice: string, tier: 'retail'|'wholesale', minimumQuantity: int, appliedTierMinimumQuantity: int|null}|null */
    public function forQuantity(Listing $listing, ?ListingVariant $variant, int $quantity): ?array
    {
        $wholesaleTiers = ($variant === null ? $listing->wholesalePriceTiers : $variant->wholesalePriceTiers)
            ->sortBy('minimum_quantity')
            ->values();
        $firstWholesaleTier = $wholesaleTiers->first();
        $appliedWholesaleTier = $wholesaleTiers
            ->filter(fn ($tier): bool => $quantity >= $tier->minimum_quantity)
            ->last();

        if (
            $listing->is_wholesale_enabled
            && $appliedWholesaleTier !== null
        ) {
            return [
                'unitPrice' => (string) $appliedWholesaleTier->unit_price,
                'tier' => 'wholesale',
                'minimumQuantity' => $listing->is_retail_enabled ? 1 : (int) $firstWholesaleTier->minimum_quantity,
                'appliedTierMinimumQuantity' => (int) $appliedWholesaleTier->minimum_quantity,
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
            'appliedTierMinimumQuantity' => null,
        ];
    }

    public function wholesaleMinimum(Listing $listing, ?ListingVariant $variant): ?int
    {
        $minimum = ($variant === null ? $listing->wholesalePriceTiers : $variant->wholesalePriceTiers)
            ->min('minimum_quantity');

        return $listing->is_wholesale_enabled && $minimum !== null ? (int) $minimum : null;
    }
}
