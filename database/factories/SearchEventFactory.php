<?php

namespace Database\Factories;

use App\Models\SearchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SearchEvent>
 */
class SearchEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $term = fake()->word().' '.fake()->word();

        return [
            'user_id' => null,
            'visitor_id' => fake()->uuid(),
            'term' => $term,
            'normalized_term' => Str::lower($term),
            'result_count' => fake()->numberBetween(0, 100),
            'context' => 'listings',
            'filters' => null,
            'searched_at' => now(),
        ];
    }
}
