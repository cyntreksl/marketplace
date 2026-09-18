<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rows = [
            ['name' => 'Featured Products', 'slug' => 'featured', 'rule_key' => 'featured'],
            ['name' => 'Latest Deals', 'slug' => 'deals', 'rule_key' => 'deals'],
            ['name' => 'Best Sellers', 'slug' => 'best-sellers', 'rule_key' => 'best-sellers'],
            ['name' => 'New Arrivals', 'slug' => 'new-arrivals', 'rule_key' => 'new-arrivals'],
            ['name' => 'Clearance Deals', 'slug' => 'clearance', 'rule_key' => 'clearance'],
        ];

        foreach ($rows as $index => $row) {
            DB::table('collections')->insert([
                ...$row,
                'type' => 'rule',
                'is_active' => true,
                'show_on_homepage_tile' => false,
                // False for every seeded rule collection: the homepage already renders
                // "Featured Deals" and "New Arrivals" sections from their own dedicated
                // props (bestOffers/featuredDeals/newArrivals), so defaulting this to
                // true here would duplicate those sections via collectionSections.
                'show_on_homepage_grid' => false,
                // Only "deals" was a real nav/footer link before this feature existed
                // (the static "Deals" entry). The other 4 rule collections never had
                // any nav presence, so defaulting them to visible here would add new,
                // previously-nonexistent links to every page's header and footer.
                'show_in_navigation' => $row['slug'] === 'deals',
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('collections')->where('type', 'rule')->delete();
    }
};
