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
        Schema::table('google_product_taxonomy_nodes', function (Blueprint $table) {
            $table->index(
                ['google_product_taxonomy_version_id', 'full_path'],
                'taxonomy_nodes_version_full_path_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_product_taxonomy_nodes', function (Blueprint $table) {
            $table->dropIndex('taxonomy_nodes_version_full_path_index');
        });
    }
};
