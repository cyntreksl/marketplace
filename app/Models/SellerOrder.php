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
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $ready_to_ship_at
 * @property Carbon|null $processing_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable(['number', 'customer_order_id', 'seller_profile_id', 'status', 'cancellation_reason', 'subtotal', 'shipping_charge', 'seller_earnings', 'processing_at', 'ready_to_ship_at', 'shipped_at', 'completed_at', 'delivered_at', 'cancelled_at', 'cancelled_by'])]
class SellerOrder extends Model
{
    /** @use HasFactory<SellerOrderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_charge' => 'decimal:2', 'seller_earnings' => 'decimal:2', 'processing_at' => 'datetime', 'ready_to_ship_at' => 'datetime', 'shipped_at' => 'datetime', 'completed_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime'];
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

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    /** @return HasOne<Refund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }
}
