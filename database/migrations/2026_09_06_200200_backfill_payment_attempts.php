<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->orderBy('id')
            ->chunkById(200, function ($payments): void {
                $rows = $payments->map(function ($payment): array {
                    $status = match ($payment->status) {
                        'paid' => 'succeeded',
                        'expired' => 'expired',
                        default => 'pending',
                    };

                    return [
                        'payment_id' => $payment->id,
                        'attempt_number' => 1,
                        'status' => $status,
                        'provider_session_id' => $payment->checkout_session_id,
                        'failure_code' => null,
                        'failure_summary' => null,
                        'attempted_at' => $payment->created_at,
                        'resolved_at' => match ($status) {
                            'succeeded' => $payment->paid_at ?? $payment->updated_at,
                            'expired' => $payment->updated_at,
                            default => null,
                        },
                        'created_at' => $payment->created_at,
                        'updated_at' => $payment->updated_at,
                    ];
                })->all();

                if ($rows !== []) {
                    DB::table('payment_attempts')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('payment_attempts')->delete();
    }
};
