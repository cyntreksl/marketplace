<?php

namespace Database\Factories;

use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
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
            'provider' => 'manual',
            'courier_name' => fake()->company(),
            'tracking_number' => 'MAN-'.Str::upper(Str::random(10)),
            'status' => 'courier_assigned',
            'courier_cost' => '200.00',
            'customer_shipping_charge' => '250.00',
            'status_history' => [],
        ];
    }
}
