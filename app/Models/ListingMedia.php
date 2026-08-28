<?php

namespace App\Models;

use Database\Factories\ListingMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property array<string, string>|null $variants
 * @property string|null $variant_version
 * @property string|null $processing_status
 */
#[Appends(['url'])]
#[Fillable(['listing_id', 'disk', 'path', 'source_path', 'crop_x', 'crop_y', 'crop_width', 'crop_height', 'variant_version', 'variants', 'processing_status', 'processing_error', 'type', 'sort_order'])]
class ListingMedia extends Model
{
    /** @use HasFactory<ListingMediaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'crop_x' => 'integer',
            'crop_y' => 'integer',
            'crop_width' => 'integer',
            'crop_height' => 'integer',
            'variants' => 'array',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    /** @return Attribute<string, never> */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk($this->disk)->url($this->path));
    }

    public function urlForVariant(string $variant): string
    {
        $variants = $this->variants;
        $path = is_array($variants) && is_string($variants[$variant] ?? null)
            ? $variants[$variant]
            : $this->path;

        return Storage::disk($this->disk)->url($path);
    }
}
