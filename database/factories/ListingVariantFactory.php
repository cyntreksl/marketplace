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
            'stock_quantity' => fake()->numberBetween(0, 100),
            'position' => 0,
        ];
    }
}
