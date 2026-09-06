<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAttempt> */
class PaymentAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'attempt_number' => 1,
            'status' => PaymentAttemptStatus::Pending,
            'provider_session_id' => null,
            'attempted_at' => now(),
        ];
    }
}
