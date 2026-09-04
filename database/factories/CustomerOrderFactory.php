<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrder>
 */
class CustomerOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PRO'.fake()->unique()->numerify('######'),
            'buyer_id' => User::factory(),
            'status' => 'confirmed',
            'subtotal' => '1000.00',
            'shipping_total' => '250.00',
            'total' => '1250.00',
            'shipping_address' => ['name' => fake()->name(), 'line_1' => fake()->streetAddress(), 'city' => fake()->city()],
        ];
    }
}
