<?php

namespace Database\Factories;

use App\AuctionOfferStatus;
use App\Models\Auction;
use App\Models\AuctionOffer;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionOffer>
 */
class AuctionOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'bid_id' => Bid::factory(),
            'buyer_id' => User::factory(),
            'rank' => 1,
            'unit_price' => 10500,
            'quantity' => 1,
            'status' => AuctionOfferStatus::Offered,
            'offered_at' => now(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
