<?php

namespace App\Repositories;

use App\Contracts\Repositories\ListingVariantRepository;
use App\Models\Listing;
use Illuminate\Support\Collection;

class EloquentListingVariantRepository implements ListingVariantRepository
{
    public function replaceForListing(Listing $listing, array $options, array $variants): Collection
    {
        $existingVariants = $listing->variants()->get()->keyBy('combination_key');

        foreach ($existingVariants as $existingVariant) {
            $existingVariant->forceFill(['position' => $existingVariant->position + 1000])->save();
        }

        $listing->variantOptions()->delete();

        $valueIds = [];
        $synchronizedVariants = collect();

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
            $variant = $existingVariants->pull($variantData['combination_key'])
                ?? $listing->variants()->make([
                    'seller_profile_id' => $listing->seller_profile_id,
                    'combination_key' => $variantData['combination_key'],
                ]);
            $variant->forceFill([
                'combination_key' => $variantData['combination_key'],
                'sku' => $variantData['sku'],
                'gtin' => $variantData['gtin'],
                'mpn' => $variantData['mpn'],
                'selling_price' => $variantData['selling_price'],
                'market_price' => $variantData['market_price'],
                'stock_quantity' => $variantData['stock_quantity'],
                'is_active' => $variantData['is_active'],
                'position' => $position,
            ])->save();

            $variant->optionValues()->sync(collect($variantData['selections'])
                ->map(fn (string $value, int $optionPosition): int => $valueIds[$optionPosition][$value])
                ->values()
                ->all());
            $synchronizedVariants->push($variant);
        }

        $existingVariants->each->delete();

        return $synchronizedVariants;
    }

    public function imagesExcept(Listing $listing, array $retainedCombinationKeys): Collection
    {
        return $listing->variants()
            ->whereNotIn('combination_key', $retainedCombinationKeys)
            ->with('image')
            ->get()
            ->pluck('image')
            ->filter()
            ->values();
    }

    public function deleteForListing(Listing $listing): void
    {
        $listing->variantOptions()->delete();
        $listing->variants()->delete();
    }
}
