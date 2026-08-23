<?php

namespace App\Models;

use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property array<int, array<string, string|null>>|null $status_history */
#[Fillable(['seller_order_id', 'provider', 'courier_name', 'tracking_number', 'status', 'courier_cost', 'customer_shipping_charge', 'status_history', 'exception_reason'])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['courier_cost' => 'decimal:2', 'customer_shipping_charge' => 'decimal:2', 'status_history' => 'array'];
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class)->withTrashed();
    }
}
