<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class CashOnDeliveryService
{
    public function __construct(private readonly MarketplaceSettingsService $settings) {}

    public function allows(string $total): bool
    {
        return BigDecimal::of($total)->isLessThanOrEqualTo(
            min(5000, max(0, $this->settings->integer('checkout.cod_maximum_amount', 5000))),
        );
    }

    public function ensureAllowed(string $method, string $total): void
    {
        if ($method === 'cod' && ! $this->allows($total)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Cash on delivery is available only for eligible totals of LKR 5,000 or less, including delivery.',
            ]);
        }
    }
}
