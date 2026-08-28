<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\ReturnReason;
use App\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'buyer_id' => User::factory(),
            'quantity' => 1,
            'eligibility_expires_at' => now()->addDays(7),
            'refund_amount' => '500.00',
            'reason' => ReturnReason::NotAsDescribed,
            'status' => ReturnStatus::Requested,
            'description' => fake()->sentence(),
            'evidence' => [],
        ];
    }
}
