<?php

namespace App\Models;

use Database\Factories\BuyerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['buyer_id', 'label', 'recipient_name', 'address_line_one', 'address_line_two', 'city', 'postal_code', 'phone', 'shipping_enabled', 'billing_enabled', 'is_default_shipping', 'is_default_billing'])]
class BuyerAddress extends Model
{
    /** @use HasFactory<BuyerAddressFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'shipping_enabled' => 'boolean',
            'billing_enabled' => 'boolean',
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    /** @return array<string, string|null> */
    public function snapshot(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'address_line_one' => $this->address_line_one,
            'address_line_two' => $this->address_line_two,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
        ];
    }
}
