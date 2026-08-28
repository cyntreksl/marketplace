<?php

namespace App\Models;

use Database\Factories\SellerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['number', 'customer_order_id', 'seller_profile_id', 'status', 'subtotal', 'shipping_charge', 'seller_earnings', 'ready_to_ship_at', 'completed_at', 'delivered_at'])]
class SellerOrder extends Model
{
    /** @use HasFactory<SellerOrderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_charge' => 'decimal:2', 'seller_earnings' => 'decimal:2', 'ready_to_ship_at' => 'datetime', 'completed_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    /** @return BelongsTo<CustomerOrder, $this> */
    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class)->withTrashed();
    }

    /** @return BelongsTo<SellerProfile, $this> */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class)->withTrashed();
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Shipment, $this> */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
