<?php

namespace App\Models;

use Database\Factories\ListingVariantOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['listing_id', 'name', 'position'])]
class ListingVariantOption extends Model
{
    /** @use HasFactory<ListingVariantOptionFactory> */
    use HasFactory;

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return HasMany<ListingVariantOptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ListingVariantOptionValue::class)->orderBy('position');
    }
}
