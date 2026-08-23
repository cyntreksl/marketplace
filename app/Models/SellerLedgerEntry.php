<?php

namespace App\Models;

use Database\Factories\SellerLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['seller_profile_id', 'seller_order_id', 'type', 'status', 'amount', 'currency', 'reason', 'created_by', 'available_at'])]
class SellerLedgerEntry extends Model
{
    /** @use HasFactory<SellerLedgerEntryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'available_at' => 'datetime'];
    }

    /** @return BelongsTo<SellerProfile, $this> */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class)->withTrashed();
    }

    /** @return BelongsTo<SellerOrder, $this> */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class)->withTrashed();
    }
}
