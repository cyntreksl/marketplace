<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->string('supplier_name')->nullable();
            $table->text('internal_notes')->nullable();
        });
        Schema::table('listing_variants', function (Blueprint $table): void {
            $table->decimal('cost_price', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('listing_variants', fn (Blueprint $table) => $table->dropColumn('cost_price'));
        Schema::table('listings', fn (Blueprint $table) => $table->dropColumn(['supplier_name', 'internal_notes']));
    }
};
