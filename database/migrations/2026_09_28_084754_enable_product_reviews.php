<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('marketplace_settings')
            ->where('key', 'reviews.product.enabled')
            ->update(['value' => json_encode(true, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('marketplace_settings')
            ->where('key', 'reviews.product.enabled')
            ->update(['value' => json_encode(false, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }
};
