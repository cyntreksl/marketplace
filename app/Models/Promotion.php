<?php

namespace App\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** @property Carbon|null $ends_at */
#[Fillable(['title', 'subtitle', 'cta_label', 'visual_theme', 'image_path', 'image_disk', 'artwork_alt', 'link_url', 'placement', 'sort_order', 'is_active', 'starts_at', 'ends_at'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return Storage::disk($this->image_disk ?: (string) config('filesystems.media', 'public'))->url($this->image_path);
    }

    /** @return BelongsToMany<Listing, $this> */
    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class)->withPivot('position')->orderByPivot('position');
    }
}
