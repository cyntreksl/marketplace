<?php

namespace App\Models;

use App\PaymentAttemptStatus;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $attempt_number
 * @property PaymentAttemptStatus $status
 * @property Carbon $attempted_at
 * @property Carbon|null $resolved_at
 * @property string|null $failure_summary
 */
#[Fillable(['payment_id', 'attempt_number', 'status', 'provider_session_id', 'failure_code', 'failure_summary', 'attempted_at', 'resolved_at'])]
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'attempted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }
}
