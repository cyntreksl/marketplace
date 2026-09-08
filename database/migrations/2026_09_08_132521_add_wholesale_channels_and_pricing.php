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
        Schema::table('listings', function (Blueprint $table): void {
            $table->boolean('is_retail_enabled')->default(true)->after('listing_type')->index();
            $table->boolean('is_wholesale_enabled')->default(false)->after('is_retail_enabled')->index();
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('sale_price');
            $table->unsignedInteger('wholesale_min_quantity')->nullable()->after('wholesale_price');
        });

        Schema::table('listing_variants', function (Blueprint $table): void {
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('market_price');
            $table->unsignedInteger('wholesale_min_quantity')->nullable()->after('wholesale_price');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('pricing_tier', 20)->default('retail')->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('pricing_tier');
        });

        Schema::table('listing_variants', function (Blueprint $table): void {
            $table->dropColumn(['wholesale_price', 'wholesale_min_quantity']);
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex(['is_retail_enabled']);
            $table->dropIndex(['is_wholesale_enabled']);
            $table->dropColumn([
                'is_retail_enabled',
                'is_wholesale_enabled',
                'wholesale_price',
                'wholesale_min_quantity',
            ]);
        });
    }
};
