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
        Schema::create('google_product_taxonomy_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('google_product_taxonomy_version_id')->constrained(indexName: 'taxonomy_nodes_version_fk')->cascadeOnDelete();
            $table->unsignedBigInteger('google_product_category_id');
            $table->foreignId('parent_id')->nullable()->constrained('google_product_taxonomy_nodes')->nullOnDelete();
            $table->string('name');
            $table->string('full_path');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->timestamps();
            $table->unique(['google_product_taxonomy_version_id', 'google_product_category_id'], 'taxonomy_nodes_version_google_id_unique');
            $table->index(['google_product_taxonomy_version_id', 'parent_id'], 'taxonomy_nodes_version_parent_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_product_taxonomy_nodes');
    }
};
