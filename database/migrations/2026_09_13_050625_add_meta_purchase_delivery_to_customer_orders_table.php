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
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->timestamp('meta_purchase_sent_at')->nullable()->after('meta_attribution');
            $table->string('meta_purchase_trace_id', 128)->nullable()->after('meta_purchase_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['meta_purchase_sent_at', 'meta_purchase_trace_id']);
        });
    }
};
