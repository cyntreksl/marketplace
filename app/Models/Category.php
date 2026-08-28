<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['parent_id', 'google_product_category_id', 'name', 'slug', 'commission_percentage', 'return_window_days', 'cod_enabled', 'is_active', 'is_selectable', 'is_popular', 'homepage_order', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'cod_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_taxonomy_available' => 'boolean',
            'is_selectable' => 'boolean',
            'is_popular' => 'boolean',
            'homepage_order' => 'integer',
            'commission_percentage' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function constrainStorefrontAvailability(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('deleted_at'))
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('is_taxonomy_available')
                ->orWhere('is_taxonomy_available', true));
    }

    /** @param Builder<Category> $query */
    #[Scope]
    protected function storefrontAvailable(Builder $query): void
    {
        self::constrainStorefrontAvailability($query);
    }

    public function isStorefrontAvailable(): bool
    {
        return ! $this->trashed()
            && $this->is_active
            && $this->is_taxonomy_available !== false;
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
