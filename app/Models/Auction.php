<?php

namespace App\Models;

use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $starting_price
 * @property numeric-string|null $reserve_price
 * @property numeric-string $minimum_increment
 * @property numeric-string|null $current_price
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['listing_id', 'status', 'starting_price', 'reserve_price', 'buy_now_price', 'minimum_increment', 'current_price', 'winning_bid_id', 'starts_at', 'ends_at', 'payment_due_at', 'closed_at', 'cancellation_reason'])]
class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starting_price' => 'decimal:2',
            'reserve_price' => 'decimal:2',
            'buy_now_price' => 'decimal:2',
            'minimum_increment' => 'decimal:2',
            'current_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return HasMany<Bid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class)->latest();
    }
}
