<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\WholesalePriceTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WholesalePriceTier>
 */
class WholesalePriceTierFactory extends Factory
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
            'listing_variant_id' => null,
            'minimum_quantity' => fake()->numberBetween(2, 100),
            'unit_price' => fake()->randomFloat(2, 1, 10000),
        ];
    }
}
