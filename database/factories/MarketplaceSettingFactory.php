<?php

namespace Database\Factories;

use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketplaceSetting>
 */
class MarketplaceSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'marketplace.'.Str::lower(Str::random(12)),
            'value' => ['enabled' => true],
            'group' => 'marketplace',
            'updated_by' => User::factory(),
        ];
    }
}
