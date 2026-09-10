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
            $table->text('cancellation_reason')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at')->index();
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropForeign(['return_request_id']);
            $table->dropUnique(['return_request_id']);
            $table->unsignedBigInteger('return_request_id')->nullable()->change();
            $table->foreign('return_request_id')->references('id')->on('return_requests')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->after('return_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seller_order_id');
            $table->decimal('amount', 12, 2)->nullable(false)->change();
            $table->dropForeign(['return_request_id']);
            $table->unsignedBigInteger('return_request_id')->nullable(false)->change();
            $table->unique('return_request_id');
            $table->foreign('return_request_id')->references('id')->on('return_requests')->cascadeOnDelete();
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
