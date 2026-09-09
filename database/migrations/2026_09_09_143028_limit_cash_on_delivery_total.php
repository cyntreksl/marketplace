<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('marketplace_settings')->updateOrInsert(
            ['key' => 'checkout.cod_maximum_amount'],
            ['group' => 'checkout', 'value' => json_encode(5000), 'deleted_at' => null, 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        // Do not restore a higher COD limit on rollback.
    }
};
