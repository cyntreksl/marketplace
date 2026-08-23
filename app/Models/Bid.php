<?php

namespace App\Models;

use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string $amount
 * @property numeric-string|null $maximum_amount
 */
#[Fillable(['auction_id', 'buyer_id', 'amount', 'maximum_amount', 'is_proxy'])]
class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'maximum_amount' => 'decimal:2', 'is_proxy' => 'boolean'];
    }

    /** @return BelongsTo<Auction, $this> */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }
}
