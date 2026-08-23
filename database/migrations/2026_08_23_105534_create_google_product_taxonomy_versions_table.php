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
        Schema::create('google_product_taxonomy_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version');
            $table->string('locale', 10)->default('en');
            $table->string('source_filename');
            $table->string('checksum', 64)->unique();
            $table->unsignedInteger('node_count')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['locale', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_product_taxonomy_versions');
    }
};
