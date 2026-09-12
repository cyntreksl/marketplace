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
            $table->string('seo_focus_query')->nullable()->after('seo_intro');
            $table->json('seo_supporting_queries')->nullable()->after('seo_focus_query');
            $table->date('seo_researched_at')->nullable()->after('seo_supporting_queries');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('seo_focus_query')->nullable()->after('seo_intro');
            $table->json('seo_supporting_queries')->nullable()->after('seo_focus_query');
            $table->date('seo_researched_at')->nullable()->after('seo_supporting_queries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['seo_focus_query', 'seo_supporting_queries', 'seo_researched_at']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['seo_focus_query', 'seo_supporting_queries', 'seo_researched_at']);
        });
    }
};
