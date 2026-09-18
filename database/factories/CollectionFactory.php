<?php

namespace Database\Factories;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'type' => 'manual',
            'rule_key' => null,
            'is_active' => true,
            'show_on_homepage_tile' => false,
            'show_on_homepage_grid' => false,
            'sort_order' => 0,
        ];
    }

    public function rule(string $ruleKey): static
    {
        return $this->state(fn (): array => [
            'type' => 'rule',
            'rule_key' => $ruleKey,
        ]);
    }
}
