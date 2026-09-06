<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 30)->index();
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('failure_code')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'attempt_number']);
            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
