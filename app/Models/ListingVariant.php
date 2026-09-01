<?php

namespace App\Models;

use Database\Factories\ListingVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['listing_id', 'seller_profile_id', 'combination_key', 'sku', 'stock_quantity', 'position'])]
class ListingVariant extends Model
{
    /** @use HasFactory<ListingVariantFactory> */
    use HasFactory;

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
}
