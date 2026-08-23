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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index()->after('password');
            $table->softDeletes();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('google_product_category_id')->nullable()->index()->after('parent_id');
            $table->softDeletes();
        });

        foreach (['brands', 'marketplace_settings', 'listings', 'listing_media', 'auctions', 'bids', 'watchlists', 'carts', 'cart_items', 'seller_profiles', 'customer_orders', 'seller_orders', 'order_items', 'payments', 'shipments', 'seller_ledger_entries', 'payout_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['brands', 'marketplace_settings', 'listings', 'listing_media', 'auctions', 'bids', 'watchlists', 'carts', 'cart_items', 'seller_profiles', 'customer_orders', 'seller_orders', 'order_items', 'payments', 'shipments', 'seller_ledger_entries', 'payout_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['google_product_category_id']);
            $table->dropColumn('google_product_category_id');
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
            $table->dropSoftDeletes();
        });
    }
};
