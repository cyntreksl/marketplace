<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('recipient_name', 120);
            $table->string('address_line_one');
            $table->string('address_line_two')->nullable();
            $table->string('city', 120);
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 20);
            $table->boolean('shipping_enabled')->default(true);
            $table->boolean('billing_enabled')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();

            $table->index(['buyer_id', 'shipping_enabled']);
            $table->index(['buyer_id', 'billing_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_addresses');
    }
};
