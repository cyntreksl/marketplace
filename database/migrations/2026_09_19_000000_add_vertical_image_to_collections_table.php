<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->string('vertical_image_path')->nullable()->after('homepage_banner_side');
            $table->string('vertical_image_disk')->nullable()->after('vertical_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn(['vertical_image_path', 'vertical_image_disk']);
        });
    }
};
