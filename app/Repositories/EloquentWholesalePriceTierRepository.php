<?php

namespace App\Repositories;

use App\Contracts\Repositories\WholesalePriceTierRepository;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\WholesalePriceTier;
use Illuminate\Database\Eloquent\Builder;

class EloquentWholesalePriceTierRepository implements WholesalePriceTierRepository
{
    public function replaceForListing(Listing $listing, array $tiers): void
    {
        $listing->wholesalePriceTiers()->delete();
        $listing->wholesalePriceTiers()->createMany($this->ordered($tiers));
        $listing->unsetRelation('wholesalePriceTiers');
    }

    public function replaceForVariant(ListingVariant $variant, array $tiers): void
    {
        $variant->wholesalePriceTiers()->delete();
        $variant->wholesalePriceTiers()->createMany(
            collect($this->ordered($tiers))
                ->map(fn (array $tier): array => [...$tier, 'listing_id' => $variant->listing_id])
                ->all(),
        );
        $variant->unsetRelation('wholesalePriceTiers');
    }

    public function lowestForListing(Listing $listing): ?WholesalePriceTier
    {
        return WholesalePriceTier::query()
            ->whereBelongsTo($listing)
            ->when(
                $listing->product_type === 'variant',
                fn (Builder $query): Builder => $query->whereIn(
                    'listing_variant_id',
                    ListingVariant::query()->whereBelongsTo($listing)->where('is_active', true)->select('id'),
                ),
                fn (Builder $query): Builder => $query->whereNull('listing_variant_id'),
            )
            ->orderBy('unit_price')
            ->orderBy('minimum_quantity')
            ->first();
    }

    /** @param list<array{minimum_quantity: int, unit_price: mixed}> $tiers
     * @return list<array{minimum_quantity: int, unit_price: mixed}>
     */
    private function ordered(array $tiers): array
    {
        return array_values(collect($tiers)
            ->sortBy('minimum_quantity')
            ->values()
            ->all());
    }
}
