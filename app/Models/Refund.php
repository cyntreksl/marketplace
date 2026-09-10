<?php

namespace App\Models;

use App\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property RefundStatus $status
 * @property string|null $amount
 * @property Carbon|null $completed_at
 */
#[Fillable(['return_request_id', 'seller_order_id', 'payment_id', 'method', 'amount', 'status', 'idempotency_key', 'provider_reference', 'manual_reference', 'failure_details', 'processed_by', 'completed_at'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'amount' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Refund $refund): void {
            $hasReturnRequest = $refund->return_request_id !== null;
            $hasSellerOrder = $refund->seller_order_id !== null;

            if ($hasReturnRequest === $hasSellerOrder) {
                throw new LogicException('A refund must belong to either a return request or a cancelled seller order.');
            }
        });
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
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
