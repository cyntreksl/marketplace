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
        $now = now();

        DB::table('marketplace_settings')->insertOrIgnore([
            [
                'key' => 'reviews.product.enabled',
                'value' => json_encode(false, JSON_THROW_ON_ERROR),
                'group' => 'reviews',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'reviews.seller.enabled',
                'value' => json_encode(false, JSON_THROW_ON_ERROR),
                'group' => 'reviews',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('marketplace_settings')
            ->whereIn('key', ['reviews.product.enabled', 'reviews.seller.enabled'])
            ->delete();
    }
};
