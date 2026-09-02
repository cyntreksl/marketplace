<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property array<string, mixed>|null $specifications */
#[Fillable(['seller_profile_id', 'category_id', 'brand_id', 'brand_name', 'sku', 'barcode', 'model', 'title', 'slug', 'short_description', 'description', 'condition', 'listing_type', 'product_type', 'status', 'location', 'specifications', 'warranty', 'stock_quantity', 'reserved_quantity', 'low_stock_threshold', 'allow_backorders', 'is_active', 'is_featured', 'is_best_seller', 'price', 'sale_price', 'cost_price', 'commission_percentage', 'moderation_reason', 'submitted_at', 'approved_at', 'is_best_offer', 'is_new_arrival', 'is_clearance', 'meta_title', 'meta_description'])]
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
            'cost_price' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_best_offer' => 'boolean',
            'is_new_arrival' => 'boolean',
            'allow_backorders' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_clearance' => 'boolean',
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
        return $this->hasMany(ListingMedia::class)
            ->whereNull('listing_variant_id')
            ->orderBy('sort_order');
    }

    /** @return HasMany<ListingVariantOption, $this> */
    public function variantOptions(): HasMany
    {
        return $this->hasMany(ListingVariantOption::class)->orderBy('position');
    }

    /** @return HasMany<ListingVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ListingVariant::class)->orderBy('position');
    }

    /** @return HasOne<Auction, $this> */
    public function auction(): HasOne
    {
        return $this->hasOne(Auction::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->withTrashed();
    }

    /** @return HasManyThrough<Review, OrderItem, $this> */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, OrderItem::class);
    }

    /** @return HasMany<ProductQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    /** @return HasMany<Watchlist, $this> */
    public function watchlistEntries(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    public function buyNowPrice(): ?string
    {
        if ($this->listing_type !== 'buy_now') {
            return null;
        }

        $price = $this->sale_price ?? $this->price;

        return $price === null ? null : (string) $price;
    }

    public function stockStatus(): string
    {
        $available = $this->stock_quantity - $this->reserved_quantity;

        if ($available <= 0) {
            return $this->allow_backorders ? 'backorder' : 'out_of_stock';
        }

        if ($available <= $this->low_stock_threshold) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /** @param Builder<Listing> $query */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('listings.status', 'approved')
            ->where('listings.is_active', true)
            ->whereHas('sellerProfile', fn (Builder $query) => $query->whereIn('status', ['approved', 'active']))
            ->whereHas('category', fn ($query) => Category::constrainStorefrontAvailability($query))
            ->where(function (Builder $query): void {
                $query->where('listings.listing_type', 'auction')
                    ->orWhere('listings.allow_backorders', true)
                    ->orWhereColumn('listings.stock_quantity', '>', 'listings.reserved_quantity');
            });
    }
}
