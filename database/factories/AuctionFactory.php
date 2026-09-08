<?php

namespace Database\Factories;

use App\AuctionStatus;
use App\AuctionType;
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
            'listing_id' => Listing::factory(),
            'status' => AuctionStatus::Live,
            'type' => AuctionType::Normal,
            'quantity' => 1,
            'starting_price' => 10000,
            'minimum_increment' => 500,
            'current_price' => 10000,
            'extension_window_minutes' => 5,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(6),
            'inventory_reserved_at' => now()->subHour(),
        ];
    }
}
