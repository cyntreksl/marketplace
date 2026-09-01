<?php

namespace App\Models;

use Database\Factories\ListingVariantOptionValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['listing_variant_option_id', 'value', 'position'])]
class ListingVariantOptionValue extends Model
{
    /** @use HasFactory<ListingVariantOptionValueFactory> */
    use HasFactory;

    /** @return BelongsTo<ListingVariantOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ListingVariantOption::class, 'listing_variant_option_id');
    }

    /** @return BelongsToMany<ListingVariant, $this> */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ListingVariant::class,
            'listing_variant_option_value',
            'listing_variant_option_value_id',
            'listing_variant_id',
        );
    }
}
