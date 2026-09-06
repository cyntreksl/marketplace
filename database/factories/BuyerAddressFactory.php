<?php

namespace Database\Factories;

use App\Models\BuyerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BuyerAddress> */
class BuyerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'label' => 'Home',
            'recipient_name' => fake()->name(),
            'address_line_one' => fake()->streetAddress(),
            'address_line_two' => null,
            'city' => 'Colombo',
            'postal_code' => '00100',
            'phone' => '0771234567',
            'shipping_enabled' => true,
            'billing_enabled' => false,
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ];
    }
}
