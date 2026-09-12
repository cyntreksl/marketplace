<?php

namespace Database\Factories;

use App\Models\SeoRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoRedirect>
 */
class SeoRedirectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_path' => '/old-'.fake()->unique()->slug(),
            'destination_path' => '/new-'.fake()->unique()->slug(),
            'is_active' => true,
            'hit_count' => 0,
            'last_hit_at' => null,
        ];
    }
}
