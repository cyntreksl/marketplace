<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->uuid('checkout_token')->nullable()->unique();
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('checkout_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['checkout_session_id']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['checkout_session_id', 'checkout_url', 'expires_at']);
        });
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn('checkout_token');
        });
    }
};
