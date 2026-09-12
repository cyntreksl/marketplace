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
            $table->string('contact_email')->nullable()->after('buyer_id');
            $table->char('checkout_identity_hash', 64)->nullable()->after('checkout_token');
            $table->char('guest_access_token_hash', 64)->nullable()->unique()->after('checkout_identity_hash');
            $table->boolean('marketing_opt_in')->default(false)->after('guest_access_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropUnique(['guest_access_token_hash']);
            $table->dropColumn([
                'contact_email',
                'checkout_identity_hash',
                'guest_access_token_hash',
                'marketing_opt_in',
            ]);
        });
    }
};
