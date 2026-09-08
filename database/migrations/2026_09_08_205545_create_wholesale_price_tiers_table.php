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
        Schema::create('wholesale_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minimum_quantity');
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
            $table->index(
                ['listing_id', 'listing_variant_id', 'minimum_quantity'],
                'wholesale_tiers_owner_quantity_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesale_price_tiers');
    }
};
