<?php

namespace App\Models;

use Database\Factories\ListingMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'disk', 'path', 'type', 'sort_order'])]
class ListingMedia extends Model
{
    /** @use HasFactory<ListingMediaFactory> */
    use HasFactory;

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
