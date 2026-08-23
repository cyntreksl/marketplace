<?php

namespace Database\Factories;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerProfile>
 */
class SellerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seller_type' => 'individual',
            'status' => 'approved',
            'store_name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'phone' => fake()->phoneNumber(),
            'pickup_address' => fake()->address(),
            'return_address' => fake()->address(),
            'terms_accepted_at' => now(),
            'approved_at' => now(),
        ];
    }
}
