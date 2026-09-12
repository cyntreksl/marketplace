<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->string('contact_email')->nullable(false)->change();
            $table->unsignedBigInteger('buyer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('customer_orders')->whereNull('buyer_id')->exists()) {
            throw new RuntimeException('Guest orders must be claimed or migrated before reverting guest checkout.');
        }

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_id')->nullable(false)->change();
            $table->string('contact_email')->nullable()->change();
        });
    }
};
