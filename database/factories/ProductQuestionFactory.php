<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductQuestion>
 */
class ProductQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'asked_by' => User::factory(),
            'question' => fake()->sentence().'?',
            'answer' => null,
            'answered_by' => null,
            'answered_at' => null,
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'answer' => fake()->paragraph(),
            'answered_by' => User::factory(),
            'answered_at' => now(),
        ]);
    }
}
