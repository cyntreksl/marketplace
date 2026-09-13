<?php

namespace App\Models;

use Database\Factories\CustomerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $subtotal
 * @property numeric-string $shipping_total
 * @property numeric-string $total
 * @property int|null $buyer_id
 * @property string $contact_email
 * @property string|null $guest_access_token_hash
 * @property array<string, string|null> $shipping_address
 * @property array<string, string|null>|null $billing_address
 * @property array<string, string|null>|null $meta_attribution
 * @property Carbon|null $meta_purchase_sent_at
 * @property string|null $meta_purchase_trace_id
 * @property Carbon|null $created_at
 * @property User|null $buyer
 */
#[Fillable(['checkout_token', 'checkout_identity_hash', 'guest_access_token_hash', 'marketing_opt_in', 'number', 'buyer_id', 'contact_email', 'auction_offer_id', 'status', 'subtotal', 'shipping_total', 'total', 'shipping_address', 'billing_address', 'meta_attribution'])]
#[Hidden(['checkout_identity_hash', 'guest_access_token_hash', 'meta_attribution'])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_total' => 'decimal:2', 'total' => 'decimal:2', 'shipping_address' => 'array', 'billing_address' => 'array', 'marketing_opt_in' => 'boolean', 'meta_attribution' => 'encrypted:array', 'meta_purchase_sent_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    /** @return BelongsTo<AuctionOffer, $this> */
    public function auctionOffer(): BelongsTo
    {
        return $this->belongsTo(AuctionOffer::class);
    }

    /** @return HasMany<SellerOrder, $this> */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
