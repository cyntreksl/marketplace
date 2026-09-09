<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedBigInteger('last_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
