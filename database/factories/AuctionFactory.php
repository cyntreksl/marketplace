<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->state(['listing_type' => 'auction']),
            'status' => 'live',
            'starting_price' => 10000,
            'reserve_price' => 15000,
            'minimum_increment' => 500,
            'current_price' => 10000,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(6),
        ];
    }
}
