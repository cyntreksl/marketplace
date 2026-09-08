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
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
            $table->dropUnique('auctions_listing_id_unique');
            $table->index('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
            $table->foreignId('listing_variant_id')->nullable()->after('listing_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1)->after('listing_variant_id');
            $table->string('type')->default('normal')->after('status')->index();
            $table->unsignedSmallInteger('extension_window_minutes')->default(5)->after('minimum_increment');
            $table->timestamp('inventory_reserved_at')->nullable()->after('ends_at');
            $table->timestamp('inventory_released_at')->nullable()->after('inventory_reserved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
            $table->dropForeign(['listing_variant_id']);
            $table->dropColumn([
                'listing_variant_id',
                'quantity',
                'type',
                'extension_window_minutes',
                'inventory_reserved_at',
                'inventory_released_at',
            ]);
            $table->dropIndex(['listing_id']);
            $table->unique('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
        });
    }
};
