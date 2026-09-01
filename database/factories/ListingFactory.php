<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_profile_id' => SellerProfile::factory(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraph(),
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'product_type' => 'simple',
            'status' => 'approved',
            'location' => 'Colombo',
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'price' => 25000,
            'commission_percentage' => 8,
            'approved_at' => now(),
        ];
    }
}
