<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listing_variants', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable()->after('sku');
            $table->decimal('market_price', 12, 2)->nullable()->after('selling_price');
            $table->boolean('is_active')->default(true)->after('reserved_quantity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listing_variants', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['selling_price', 'market_price', 'is_active']);
        });
    }
};
