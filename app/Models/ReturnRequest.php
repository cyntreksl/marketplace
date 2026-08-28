<?php

namespace App\Models;

use App\ReturnReason;
use App\ReturnStatus;
use Database\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['order_item_id', 'buyer_id', 'quantity', 'eligibility_expires_at', 'refund_amount', 'reason', 'status', 'description', 'evidence', 'resolution_reason', 'resolved_by', 'resolved_at', 'seller_responded_at', 'refund_ready_at'])]
class ReturnRequest extends Model
{
    /** @use HasFactory<ReturnRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => ReturnReason::class,
            'status' => ReturnStatus::class,
            'evidence' => 'array',
            'refund_amount' => 'decimal:2',
            'eligibility_expires_at' => 'datetime',
            'resolved_at' => 'datetime',
            'seller_responded_at' => 'datetime',
            'refund_ready_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    /** @return HasOne<Refund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }
}
