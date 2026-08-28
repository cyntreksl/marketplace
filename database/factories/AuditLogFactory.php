<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => fake()->randomElement(['listing.created', 'listing.updated']),
            'auditable_type' => User::class,
            'auditable_id' => User::factory(),
            'before' => null,
            'after' => ['status' => 'active'],
            'reason' => fake()->sentence(),
        ];
    }
}
