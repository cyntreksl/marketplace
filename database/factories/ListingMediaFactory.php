<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingMedia>
 */
class ListingMediaFactory extends Factory
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
            'disk' => 'public',
            'path' => 'listings/'.fake()->uuid().'.webp',
            'source_path' => null,
            'variant_version' => null,
            'variants' => null,
            'processing_status' => null,
            'type' => 'image',
            'sort_order' => 0,
        ];
    }
}
