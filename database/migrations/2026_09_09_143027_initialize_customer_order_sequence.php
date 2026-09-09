<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('customer_orders')->select('number')->cursor() as $order) {
                if (preg_match('/^PRO([0-9]+)$/', $order->number, $matches) === 1 && (int) $matches[1] >= 1125) {
                    throw new RuntimeException('Cannot initialize order numbering: an existing PRO number is 1125 or higher.');
                }
            }
            DB::table('order_number_sequences')->insert(['name' => 'customer', 'last_number' => 1124]);
        });
    }

    public function down(): void
    {
        // Preserve allocated numbers on rollback; restoring the sequence requires a forward migration.
    }
};
