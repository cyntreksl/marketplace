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
        Schema::table('listings', function (Blueprint $table) {
            $table->string('sku', 100)->nullable()->after('brand_name');
            $table->string('barcode', 100)->nullable()->after('sku');
            $table->string('short_description', 160)->nullable()->after('slug');
            $table->string('product_type')->default('simple')->after('listing_type')->index();
            $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price');
            $table->unsignedInteger('low_stock_threshold')->default(0)->after('reserved_quantity');
            $table->boolean('allow_backorders')->default(false)->after('low_stock_threshold');
            $table->boolean('is_active')->default(true)->after('allow_backorders')->index();
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->boolean('is_best_seller')->default(false)->after('is_featured');
            $table->string('meta_title')->nullable()->after('is_new_arrival');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->unique(['seller_profile_id', 'sku']);
            $table->unique(['seller_profile_id', 'barcode']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
            $table->string('title')->nullable()->change();
            $table->string('slug')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->string('condition')->nullable()->change();
            $table->string('location')->nullable()->change();
            $table->decimal('commission_percentage', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropUnique(['seller_profile_id', 'sku']);
            $table->dropUnique(['seller_profile_id', 'barcode']);
            $table->dropIndex(['product_type']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'sku',
                'barcode',
                'short_description',
                'product_type',
                'cost_price',
                'low_stock_threshold',
                'allow_backorders',
                'is_active',
                'is_featured',
                'is_best_seller',
                'meta_title',
                'meta_description',
            ]);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
            $table->string('title')->nullable(false)->change();
            $table->string('slug')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->string('condition')->nullable(false)->change();
            $table->string('location')->nullable(false)->change();
            $table->decimal('commission_percentage', 5, 2)->nullable(false)->change();
        });
    }
};
