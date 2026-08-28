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
        Schema::table('listing_media', function (Blueprint $table) {
            $table->string('source_path')->nullable()->after('path');
            $table->unsignedInteger('crop_x')->nullable()->after('source_path');
            $table->unsignedInteger('crop_y')->nullable()->after('crop_x');
            $table->unsignedInteger('crop_width')->nullable()->after('crop_y');
            $table->unsignedInteger('crop_height')->nullable()->after('crop_width');
            $table->uuid('variant_version')->nullable()->after('crop_height');
            $table->json('variants')->nullable()->after('variant_version');
            $table->string('processing_status')->nullable()->after('variants');
            $table->text('processing_error')->nullable()->after('processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listing_media', function (Blueprint $table) {
            $table->dropColumn([
                'source_path',
                'crop_x',
                'crop_y',
                'crop_width',
                'crop_height',
                'variant_version',
                'variants',
                'processing_status',
                'processing_error',
            ]);
        });
    }
};
