<?php

namespace App\Models;

use App\AuctionOfferStatus;
use Database\Factories\AuctionOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property AuctionOfferStatus $status
 * @property numeric-string $unit_price
 * @property Carbon|null $expires_at
 */
#[Fillable(['auction_id', 'bid_id', 'buyer_id', 'rank', 'unit_price', 'quantity', 'status', 'offered_at', 'expires_at', 'accepted_at', 'paid_at', 'expired_at'])]
class AuctionOffer extends Model
{
    /** @use HasFactory<AuctionOfferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AuctionOfferStatus::class,
            'unit_price' => 'decimal:2',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Auction, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /** @return BelongsTo<Bid, $this> */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    /** @return HasOne<CustomerOrder, $this> */
    public function order(): HasOne
    {
        return $this->hasOne(CustomerOrder::class);
    }
}
