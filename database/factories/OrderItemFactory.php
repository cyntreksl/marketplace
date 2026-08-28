<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_order_id' => SellerOrder::factory(),
            'listing_id' => Listing::factory(),
            'title' => fake()->words(3, true),
            'quantity' => 2,
            'unit_price' => '500.00',
            'commission_percentage' => '10.00',
            'commission_amount' => '100.00',
            'total' => '1000.00',
        ];
    }
}
