<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SellerOrder>
 */
class SellerOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'SELL-'.Str::upper(fake()->unique()->bothify('########')),
            'customer_order_id' => CustomerOrder::factory(),
            'seller_profile_id' => SellerProfile::factory(),
            'status' => 'paid',
            'subtotal' => '1000.00',
            'shipping_charge' => '250.00',
            'seller_earnings' => '900.00',
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'ready_to_ship_at' => now()->subDay(),
            'completed_at' => now(),
            'delivered_at' => now(),
        ]);
    }
}
