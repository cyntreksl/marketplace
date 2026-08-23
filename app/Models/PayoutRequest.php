<?php

namespace App\Models;

use Database\Factories\PayoutRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seller_profile_id', 'amount', 'status', 'payment_reference', 'proof_path', 'reason', 'reviewed_by', 'settled_at'])]
class PayoutRequest extends Model
{
    /** @use HasFactory<PayoutRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'settled_at' => 'datetime'];
    }

    /** @return BelongsTo<SellerProfile, $this> */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }
}
