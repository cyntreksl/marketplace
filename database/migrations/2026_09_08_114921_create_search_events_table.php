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
        Schema::create('search_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visitor_id')->index();
            $table->string('term', 120);
            $table->string('normalized_term', 120);
            $table->unsignedInteger('result_count');
            $table->string('context', 40);
            $table->json('filters')->nullable();
            $table->timestamp('searched_at');

            $table->index(['normalized_term', 'searched_at']);
            $table->index(['result_count', 'searched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_events');
    }
};
