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
        Schema::create('listing_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(['listing_id', 'position']);
        });

        Schema::create('listing_variant_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_variant_option_id')->constrained()->cascadeOnDelete();
            $table->string('value', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['listing_variant_option_id', 'position'], 'listing_option_value_position_unique');
        });

        Schema::create('listing_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->char('combination_key', 64);
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['listing_id', 'combination_key']);
            $table->unique(['seller_profile_id', 'sku']);
            $table->unique(['listing_id', 'position']);
        });

        Schema::create('listing_variant_option_value', function (Blueprint $table) {
            $table->foreignId('listing_variant_id');
            $table->foreignId('listing_variant_option_value_id');
            $table->foreign('listing_variant_id', 'listing_variant_value_variant_fk')
                ->references('id')
                ->on('listing_variants')
                ->cascadeOnDelete();
            $table->foreign('listing_variant_option_value_id', 'listing_variant_value_option_fk')
                ->references('id')
                ->on('listing_variant_option_values')
                ->cascadeOnDelete();
            $table->primary(
                ['listing_variant_id', 'listing_variant_option_value_id'],
                'listing_variant_value_primary',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_variant_option_value');
        Schema::dropIfExists('listing_variants');
        Schema::dropIfExists('listing_variant_option_values');
        Schema::dropIfExists('listing_variant_options');
    }
};
