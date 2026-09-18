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
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->string('rule_key', 30)->nullable()->unique();
            $table->string('image_path')->nullable();
            $table->string('image_disk')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->string('banner_image_disk')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_homepage_tile')->default(false);
            $table->boolean('show_on_homepage_grid')->default(false);
            $table->boolean('show_in_navigation')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->text('seo_intro')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
            $table->index(['type', 'rule_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
