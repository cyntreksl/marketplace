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
        if (! Schema::hasColumn('listing_variants', 'reserved_quantity')) {
            Schema::table('listing_variants', function (Blueprint $table) {
                $table->unsignedInteger('reserved_quantity')->default(0)->after('stock_quantity');
            });
        }

        if (! Schema::hasIndex('cart_items', 'cart_items_cart_id_index')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->index('cart_id');
            });
        }

        if (! Schema::hasColumn('cart_items', 'listing_variant_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropUnique(['cart_id', 'listing_id']);
                $table->foreignId('listing_variant_id')->nullable()->after('listing_id')->constrained()->nullOnDelete();
                $table->string('selection_key', 64)->default('base')->after('listing_variant_id');
                $table->unique(['cart_id', 'listing_id', 'selection_key']);
            });
        }

        if (! Schema::hasColumn('order_items', 'listing_variant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('listing_variant_id')->nullable()->after('listing_id')->constrained()->nullOnDelete();
                $table->string('variant_sku', 100)->nullable()->after('title');
                $table->json('variant_options')->nullable()->after('variant_sku');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('listing_variant_id');
            $table->dropColumn(['variant_sku', 'variant_options']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'listing_id', 'selection_key']);
            $table->dropConstrainedForeignId('listing_variant_id');
            $table->dropColumn('selection_key');
            $table->unique(['cart_id', 'listing_id']);
            $table->dropIndex(['cart_id']);
        });

        Schema::table('listing_variants', function (Blueprint $table) {
            $table->dropColumn('reserved_quantity');
        });
    }
};
