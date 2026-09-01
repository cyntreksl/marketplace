<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['seller_order_id', 'listing_id', 'listing_variant_id', 'title', 'variant_sku', 'variant_options', 'quantity', 'unit_price', 'commission_percentage', 'commission_amount', 'total'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['variant_options' => 'array', 'unit_price' => 'decimal:2', 'commission_percentage' => 'decimal:2', 'commission_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class)->withTrashed();
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    /** @return BelongsTo<ListingVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ListingVariant::class, 'listing_variant_id');
    }

    /** @return HasMany<ReturnRequest, $this> */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
