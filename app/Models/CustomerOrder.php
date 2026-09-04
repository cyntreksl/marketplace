<?php

namespace App\Models;

use Database\Factories\CustomerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $subtotal
 * @property numeric-string $shipping_total
 * @property numeric-string $total
 * @property array<string, string|null> $shipping_address
 * @property Carbon|null $created_at
 */
#[Fillable(['number', 'buyer_id', 'status', 'subtotal', 'shipping_total', 'total', 'shipping_address'])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_total' => 'decimal:2', 'total' => 'decimal:2', 'shipping_address' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    /** @return HasMany<SellerOrder, $this> */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
