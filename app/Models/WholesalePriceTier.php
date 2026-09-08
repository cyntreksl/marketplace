<?php

namespace App\Models;

use Database\Factories\WholesalePriceTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'listing_variant_id', 'minimum_quantity', 'unit_price'])]
class WholesalePriceTier extends Model
{
    /** @use HasFactory<WholesalePriceTierFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'minimum_quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<ListingVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ListingVariant::class, 'listing_variant_id');
    }
}
