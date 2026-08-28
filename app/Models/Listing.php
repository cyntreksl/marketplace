<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['seller_profile_id', 'category_id', 'brand_id', 'brand_name', 'title', 'slug', 'description', 'condition', 'listing_type', 'status', 'location', 'specifications', 'warranty', 'stock_quantity', 'reserved_quantity', 'price', 'sale_price', 'commission_percentage', 'moderation_reason', 'submitted_at', 'approved_at'])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SellerProfile, $this> */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class)->withTrashed();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /** @return HasMany<ListingMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ListingMedia::class)->orderBy('sort_order');
    }

    /** @return HasOne<Auction, $this> */
    public function auction(): HasOne
    {
        return $this->hasOne(Auction::class);
    }

    /** @param Builder<Listing> $query */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', 'approved')
            ->whereHas('sellerProfile', fn (Builder $query) => $query->whereIn('status', ['approved', 'active']))
            ->where(function (Builder $query): void {
                $query->where('listing_type', 'auction')
                    ->orWhereColumn('stock_quantity', '>', 'reserved_quantity');
            });
    }
}
