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
            $table->unsignedBigInteger('sold_count_baseline')->default(0)->after('is_clearance');
            $table->unsignedBigInteger('watch_count_baseline')->default(0)->after('sold_count_baseline');
            $table->unsignedBigInteger('view_count_baseline')->default(0)->after('watch_count_baseline');
            $table->unsignedBigInteger('view_count')->default(0)->after('view_count_baseline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn([
                'sold_count_baseline',
                'watch_count_baseline',
                'view_count_baseline',
                'view_count',
            ]);
        });
    }
};
