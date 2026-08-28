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
        DB::table('categories')
            ->whereNotNull('google_product_category_id')
            ->update([
                'is_taxonomy_available' => DB::raw('is_active'),
                'is_active' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('categories')
            ->whereNotNull('google_product_category_id')
            ->update([
                'is_active' => DB::raw('is_taxonomy_available'),
                'is_taxonomy_available' => null,
            ]);
    }
};
