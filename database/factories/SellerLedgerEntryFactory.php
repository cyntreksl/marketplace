<?php

namespace Database\Factories;

use App\Models\SellerLedgerEntry;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerLedgerEntry>
 */
class SellerLedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_profile_id' => SellerProfile::factory(),
            'seller_order_id' => SellerOrder::factory(),
            'type' => 'sale',
            'status' => 'pending',
            'amount' => '1000.00',
            'currency' => 'LKR',
            'reason' => fake()->sentence(),
            'available_at' => now()->addDays(7),
        ];
    }
}
