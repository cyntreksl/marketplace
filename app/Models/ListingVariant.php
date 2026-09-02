<?php

namespace App\Models;

use Database\Factories\ListingVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['listing_id', 'seller_profile_id', 'combination_key', 'sku', 'selling_price', 'market_price', 'stock_quantity', 'reserved_quantity', 'is_active', 'position'])]
class ListingVariant extends Model
{
    /** @use HasFactory<ListingVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'market_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function buyNowPrice(): ?string
    {
        return $this->selling_price === null ? null : (string) $this->selling_price;
    }

    public function availableQuantity(): int
    {
        if (! $this->is_active) {
            return 0;
        }

        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<SellerProfile, $this> */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /** @return BelongsToMany<ListingVariantOptionValue, $this> */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ListingVariantOptionValue::class,
            'listing_variant_option_value',
            'listing_variant_id',
            'listing_variant_option_value_id',
        )->with('option');
    }

    /** @return HasOne<ListingMedia, $this> */
    public function image(): HasOne
    {
        return $this->hasOne(ListingMedia::class);
    }
}
