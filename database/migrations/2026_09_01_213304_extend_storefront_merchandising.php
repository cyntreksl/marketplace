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
        Schema::table('listings', function (Blueprint $table): void {
            $table->boolean('is_clearance')->default(false)->after('is_new_arrival');
            $table->index(['status', 'is_clearance', 'created_at'], 'listings_clearance_index');
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('logo_disk')->nullable()->after('logo_path');
            $table->boolean('is_featured')->default(false)->after('logo_disk');
            $table->unsignedSmallInteger('homepage_order')->nullable()->after('is_featured');
            $table->index(['is_featured', 'homepage_order']);
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->string('subtitle')->nullable()->after('title');
            $table->string('cta_label', 80)->nullable()->after('subtitle');
            $table->string('visual_theme', 30)->default('orange')->after('cta_label');
            $table->string('artwork_alt')->nullable()->after('image_disk');
        });

        Schema::create('listing_promotion', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['promotion_id', 'listing_id']);
            $table->unique(['promotion_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_promotion');

        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropColumn(['subtitle', 'cta_label', 'visual_theme', 'artwork_alt']);
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->dropIndex(['is_featured', 'homepage_order']);
            $table->dropColumn(['logo_path', 'logo_disk', 'is_featured', 'homepage_order']);
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex('listings_clearance_index');
            $table->dropColumn('is_clearance');
        });
    }
};
