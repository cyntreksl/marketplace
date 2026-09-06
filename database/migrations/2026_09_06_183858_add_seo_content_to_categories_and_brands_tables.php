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
            $table->string('seo_title')->nullable()->after('slug');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->text('seo_intro')->nullable()->after('seo_description');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('seo_title')->nullable()->after('slug');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->text('seo_intro')->nullable()->after('seo_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'seo_intro']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'seo_intro']);
        });
    }
};
