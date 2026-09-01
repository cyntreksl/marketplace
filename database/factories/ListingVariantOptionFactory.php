<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingVariantOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingVariantOption>
 */
class ListingVariantOptionFactory extends Factory
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
            'name' => fake()->randomElement(['Color', 'Size', 'Material']),
            'position' => 0,
        ];
    }
}
