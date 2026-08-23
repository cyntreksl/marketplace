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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('seller_type')->index();
            $table->string('status')->default('pending_review')->index();
            $table->string('store_name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->text('pickup_address')->nullable();
            $table->text('return_address')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('bank_account_details')->nullable();
            $table->json('documents')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('commission_percentage', 5, 2)->default(8);
            $table->unsignedInteger('return_window_days')->default(7);
            $table->boolean('cod_enabled')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('marketplace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('group')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('condition')->index();
            $table->string('listing_type')->index();
            $table->string('status')->default('draft')->index();
            $table->string('location');
            $table->json('specifications')->nullable();
            $table->string('warranty')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('commission_percentage', 5, 2);
            $table->text('moderation_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'listing_type', 'created_at']);
            $table->index(['category_id', 'condition', 'price']);
        });

        Schema::create('listing_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('type')->default('image');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->decimal('starting_price', 12, 2);
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->decimal('buy_now_price', 12, 2)->nullable();
            $table->decimal('minimum_increment', 12, 2);
            $table->decimal('current_price', 12, 2)->nullable();
            $table->unsignedBigInteger('winning_bid_id')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('maximum_amount', 12, 2)->nullable();
            $table->boolean('is_proxy')->default(false);
            $table->timestamps();
            $table->index(['auction_id', 'amount']);
            $table->index(['auction_id', 'created_at']);
        });

        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['buyer_id', 'listing_id']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'listing_id']);
        });

        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending_payment')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->json('shipping_address');
            $table->timestamps();
        });

        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending_payment')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_charge', 12, 2)->default(0);
            $table->decimal('seller_earnings', 12, 2)->default(0);
            $table->timestamp('ready_to_ship_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['seller_profile_id', 'status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->string('method')->index();
            $table->string('status')->default('pending')->index();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('idempotency_key')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('proof_path')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('manual');
            $table->string('courier_name')->nullable();
            $table->string('tracking_number')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->decimal('courier_cost', 12, 2)->default(0);
            $table->decimal('customer_shipping_charge', 12, 2)->default(0);
            $table->json('status_history')->nullable();
            $table->text('exception_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('LKR');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending')->index();
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('reason')->index();
            $table->string('status')->default('requested')->index();
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'buyer_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->string('placement')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('seller_ledger_entries');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('watchlists');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('auctions');
        Schema::dropIfExists('listing_media');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('marketplace_settings');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
