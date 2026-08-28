<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'method' => 'stripe',
            'status' => 'paid',
            'provider_reference' => 'pi_'.Str::lower(Str::random(24)),
            'idempotency_key' => (string) Str::uuid(),
            'amount' => '1250.00',
            'paid_at' => now(),
        ];
    }
}
