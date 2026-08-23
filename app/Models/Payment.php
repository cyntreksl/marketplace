<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property numeric-string $amount */
#[Fillable(['customer_order_id', 'method', 'status', 'provider_reference', 'idempotency_key', 'amount', 'proof_path', 'provider_payload', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'provider_payload' => 'array', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<CustomerOrder, $this> */
    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }
}
