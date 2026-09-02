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
        Schema::table('listings', function (Blueprint $table) {
            $table->string('gtin', 14)->nullable()->after('barcode');
            $table->string('mpn', 100)->nullable()->after('gtin');
        });

        Schema::table('listing_variants', function (Blueprint $table) {
            $table->string('gtin', 14)->nullable()->after('sku');
            $table->string('mpn', 100)->nullable()->after('gtin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listing_variants', function (Blueprint $table) {
            $table->dropColumn(['gtin', 'mpn']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['gtin', 'mpn']);
        });
    }
};
