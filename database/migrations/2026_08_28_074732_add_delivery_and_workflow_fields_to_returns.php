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
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable()->after('completed_at')->index();
        });

        Schema::table('return_requests', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1)->after('buyer_id');
            $table->timestamp('eligibility_expires_at')->nullable()->after('quantity')->index();
            $table->decimal('refund_amount', 12, 2)->nullable()->after('eligibility_expires_at');
            $table->timestamp('seller_responded_at')->nullable()->after('resolved_at');
            $table->timestamp('refund_ready_at')->nullable()->after('seller_responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropIndex(['eligibility_expires_at']);
            $table->dropColumn(['quantity', 'eligibility_expires_at', 'refund_amount', 'seller_responded_at', 'refund_ready_at']);
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropIndex(['delivered_at']);
            $table->dropColumn('delivered_at');
        });
    }
};
