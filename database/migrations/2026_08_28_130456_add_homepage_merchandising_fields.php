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
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('is_popular')->default(false)->index();
            $table->unsignedTinyInteger('homepage_order')->nullable()->unique();
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->boolean('is_best_offer')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->index(['status', 'is_best_offer', 'created_at'], 'listings_best_offer_index');
            $table->index(['status', 'is_new_arrival', 'created_at'], 'listings_new_arrival_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex('listings_best_offer_index');
            $table->dropIndex('listings_new_arrival_index');
            $table->dropColumn(['is_best_offer', 'is_new_arrival']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['homepage_order']);
            $table->dropIndex(['is_popular']);
            $table->dropColumn(['is_popular', 'homepage_order']);
        });
    }
};
