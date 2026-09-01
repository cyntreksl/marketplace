<?php

namespace App\Repositories;

use App\Contracts\Repositories\ListingVariantRepository;
use App\Models\Listing;

class EloquentListingVariantRepository implements ListingVariantRepository
{
    public function replaceForListing(Listing $listing, array $options, array $variants): void
    {
        $listing->variantOptions()->delete();
        $listing->variants()->delete();

        $valueIds = [];

        foreach ($options as $optionPosition => $optionData) {
            $option = $listing->variantOptions()->create([
                'name' => $optionData['name'],
                'position' => $optionPosition,
            ]);

            foreach ($optionData['values'] as $valuePosition => $value) {
                $optionValue = $option->values()->create([
                    'value' => $value,
                    'position' => $valuePosition,
                ]);
                $valueIds[$optionPosition][$value] = $optionValue->id;
            }
        }

        foreach ($variants as $position => $variantData) {
            $variant = $listing->variants()->create([
                'seller_profile_id' => $listing->seller_profile_id,
                'combination_key' => $variantData['combination_key'],
                'sku' => $variantData['sku'],
                'stock_quantity' => $variantData['stock_quantity'],
                'position' => $position,
            ]);

            $variant->optionValues()->sync(collect($variantData['selections'])
                ->map(fn (string $value, int $optionPosition): int => $valueIds[$optionPosition][$value])
                ->values()
                ->all());
        }
    }

    public function deleteForListing(Listing $listing): void
    {
        $listing->variantOptions()->delete();
        $listing->variants()->delete();
    }
}
