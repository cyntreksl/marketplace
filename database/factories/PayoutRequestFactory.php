<?php

namespace Database\Factories;

use App\Models\PayoutRequest;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutRequest>
 */
class PayoutRequestFactory extends Factory
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
            'amount' => '1000.00',
            'status' => 'pending',
            'reason' => fake()->sentence(),
        ];
    }
}
