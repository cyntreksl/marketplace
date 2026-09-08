<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('listings')
            ->select(['id', 'wholesale_min_quantity', 'wholesale_price'])
            ->where('product_type', 'simple')
            ->whereNotNull('wholesale_min_quantity')
            ->whereNotNull('wholesale_price')
            ->orderBy('id')
            ->chunkById(500, function ($listings): void {
                $timestamp = now();
                DB::table('wholesale_price_tiers')->insertOrIgnore(
                    $listings->map(fn ($listing): array => [
                        'listing_id' => $listing->id,
                        'listing_variant_id' => null,
                        'minimum_quantity' => $listing->wholesale_min_quantity,
                        'unit_price' => $listing->wholesale_price,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all(),
                );
            });

        DB::table('listing_variants')
            ->select(['id', 'listing_id', 'wholesale_min_quantity', 'wholesale_price'])
            ->whereNotNull('wholesale_min_quantity')
            ->whereNotNull('wholesale_price')
            ->orderBy('id')
            ->chunkById(500, function ($variants): void {
                $timestamp = now();
                DB::table('wholesale_price_tiers')->insertOrIgnore(
                    $variants->map(fn ($variant): array => [
                        'listing_id' => $variant->listing_id,
                        'listing_variant_id' => $variant->id,
                        'minimum_quantity' => $variant->wholesale_min_quantity,
                        'unit_price' => $variant->wholesale_price,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all(),
                );
            });
    }

    /** Existing wholesale data is intentionally preserved during rollback. */
    public function down(): void {}
};
