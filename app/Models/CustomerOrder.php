<?php

namespace App\Models;

use Database\Factories\CustomerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'buyer_id', 'status', 'subtotal', 'shipping_total', 'total', 'shipping_address'])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_total' => 'decimal:2', 'total' => 'decimal:2', 'shipping_address' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
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
