<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $amount
 * @property Carbon|null $expires_at
 */
#[Fillable(['checkout_session_id', 'checkout_url', 'expires_at', 'customer_order_id', 'method', 'status', 'provider_reference', 'idempotency_key', 'amount', 'proof_path', 'provider_payload', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'amount' => 'decimal:2', 'provider_payload' => 'array', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<CustomerOrder, $this> */
    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class)->withTrashed();
    }
}
