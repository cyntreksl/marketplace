<?php

namespace App\Models;

use App\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property RefundStatus $status
 * @property string $amount
 * @property Carbon|null $completed_at
 */
#[Fillable(['return_request_id', 'payment_id', 'method', 'amount', 'status', 'idempotency_key', 'provider_reference', 'manual_reference', 'failure_details', 'processed_by', 'completed_at'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'amount' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }
}
