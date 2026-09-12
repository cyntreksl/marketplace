<?php

namespace Database\Factories;

use App\Models\Guide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guide>
 */
class GuideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'slug' => str($title)->slug(),
            'excerpt' => fake()->paragraph(),
            'quick_answer' => fake()->paragraph(),
            'sections' => [
                ['heading' => 'What to compare', 'paragraphs' => [fake()->paragraph()]],
            ],
            'buying_checklist' => [fake()->sentence()],
            'seo_title' => $title.' - ProDeals.lk',
            'seo_description' => fake()->text(155),
            'primary_query' => str($title)->lower()->toString(),
            'supporting_queries' => [fake()->words(3, true)],
            'trends_researched_at' => today(),
            'status' => 'draft',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }
}
