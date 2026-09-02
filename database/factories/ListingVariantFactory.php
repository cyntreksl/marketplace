<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ListingVariant>
 */
class ListingVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'seller_profile_id' => fn (array $attributes): int => Listing::query()
                ->findOrFail((int) $attributes['listing_id'])
                ->seller_profile_id,
            'combination_key' => hash('sha256', fake()->unique()->uuid()),
            'sku' => Str::upper(fake()->unique()->bothify('SKU-####-??')),
            'gtin' => null,
            'mpn' => null,
            'selling_price' => fn (array $attributes): string => Listing::query()
                ->findOrFail((int) $attributes['listing_id'])
                ->buyNowPrice() ?? '1000.00',
            'market_price' => null,
            'stock_quantity' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'position' => 0,
        ];
    }
}
