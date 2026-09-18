<?php

namespace App\Models;

use App\CollectionType;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property CollectionType $type
 */
#[Fillable(['name', 'slug', 'description', 'type', 'rule_key', 'image_path', 'image_disk', 'banner_image_path', 'banner_image_disk', 'homepage_banner_side', 'is_active', 'show_on_homepage_tile', 'show_on_homepage_grid', 'show_in_navigation', 'sort_order', 'seo_title', 'seo_description', 'seo_intro'])]
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'is_active' => 'boolean',
            'show_on_homepage_tile' => 'boolean',
            'show_on_homepage_grid' => 'boolean',
            'show_in_navigation' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsToMany<Listing, $this> */
    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'collection_listing')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function isManual(): bool
    {
        return $this->type === CollectionType::Manual;
    }

    public function imageUrl(): ?string
    {
        return $this->artworkUrl($this->image_path, $this->image_disk);
    }

    public function bannerImageUrl(): ?string
    {
        return $this->artworkUrl($this->banner_image_path, $this->banner_image_disk);
    }

    private function artworkUrl(?string $path, ?string $disk): ?string
    {
        if ($path === null) {
            return null;
        }

        return Storage::disk($disk ?: (string) config('filesystems.media', 'public'))->url($path);
    }
}
