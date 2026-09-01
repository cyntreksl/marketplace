<?php

namespace Database\Factories;

use App\Models\ListingVariantOption;
use App\Models\ListingVariantOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingVariantOptionValue>
 */
class ListingVariantOptionValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_variant_option_id' => ListingVariantOption::factory(),
            'value' => fake()->word(),
            'position' => 0,
        ];
    }
}
