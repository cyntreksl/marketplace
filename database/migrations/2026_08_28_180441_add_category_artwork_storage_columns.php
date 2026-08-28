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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_disk')->nullable()->after('image_path');
            $table->string('banner_image_path')->nullable()->after('image_disk');
            $table->string('banner_image_disk')->nullable()->after('banner_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image_disk', 'banner_image_path', 'banner_image_disk']);
        });
    }
};
