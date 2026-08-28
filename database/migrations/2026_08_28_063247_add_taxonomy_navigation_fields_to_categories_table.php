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
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['google_product_category_id']);
            $table->unique('google_product_category_id', 'categories_google_product_category_id_unique');
            $table->boolean('is_selectable')->default(true)->index()->after('is_active');
            $table->unsignedSmallInteger('sort_order')->default(0)->index()->after('is_selectable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_google_product_category_id_unique');
            $table->index('google_product_category_id');
            $table->dropIndex(['sort_order']);
            $table->dropIndex(['is_selectable']);
            $table->dropColumn(['is_selectable', 'sort_order']);
        });
    }
};
