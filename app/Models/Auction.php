<?php

namespace App\Models;

use App\AuctionStatus;
use App\AuctionType;
use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $starting_price
 * @property numeric-string|null $reserve_price
 * @property numeric-string $minimum_increment
 * @property numeric-string|null $current_price
 * @property AuctionType $type
 * @property AuctionStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['listing_id', 'listing_variant_id', 'quantity', 'status', 'type', 'starting_price', 'minimum_increment', 'extension_window_minutes', 'current_price', 'winning_bid_id', 'starts_at', 'ends_at', 'inventory_reserved_at', 'inventory_released_at', 'payment_due_at', 'closed_at', 'cancellation_reason'])]
class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AuctionStatus::class,
            'type' => AuctionType::class,
            'starting_price' => 'decimal:2',
            'reserve_price' => 'decimal:2',
            'buy_now_price' => 'decimal:2',
            'minimum_increment' => 'decimal:2',
            'current_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'inventory_reserved_at' => 'datetime',
            'inventory_released_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    /** @return BelongsTo<ListingVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ListingVariant::class, 'listing_variant_id');
    }

    /** @return BelongsTo<Bid, $this> */
    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }

    /** @return HasMany<Bid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class)->latest();
    }

    /** @return HasMany<AuctionOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(AuctionOffer::class)->orderBy('rank');
    }
}
