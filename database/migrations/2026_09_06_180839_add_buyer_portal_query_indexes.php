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
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->index(['buyer_id', 'status', 'created_at'], 'customer_orders_buyer_stage_created_index');
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->index(['customer_order_id', 'status'], 'seller_orders_customer_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->dropIndex('customer_orders_buyer_stage_created_index');
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropIndex('seller_orders_customer_status_index');
        });
    }
};
