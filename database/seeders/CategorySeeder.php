<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Mobile Phones', 'Laptops', 'Cameras', 'Gaming', 'Audio'] as $name) {
            Category::query()->updateOrCreate(['slug' => str($name)->slug()->toString()], [
                'name' => $name,
                'commission_percentage' => 8,
                'return_window_days' => 7,
                'cod_enabled' => true,
                'is_active' => true,
            ]);
        }
    }
}
