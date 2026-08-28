<?php

namespace Database\Factories;

use App\Models\Refund;
use App\Models\ReturnRequest;
use App\RefundStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_request_id' => ReturnRequest::factory(),
            'method' => 'stripe',
            'amount' => '500.00',
            'status' => RefundStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
