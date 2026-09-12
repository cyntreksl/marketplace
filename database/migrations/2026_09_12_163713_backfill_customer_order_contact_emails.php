<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('customer_orders')
            ->whereNull('contact_email')
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                $emails = DB::table('users')
                    ->whereIn('id', $orders->pluck('buyer_id')->filter())
                    ->pluck('email', 'id');

                foreach ($orders as $order) {
                    $email = $emails->get($order->buyer_id);

                    if (is_string($email)) {
                        DB::table('customer_orders')->where('id', $order->id)->update(['contact_email' => $email]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The copied email snapshots are intentionally retained during rollback.
    }
};
