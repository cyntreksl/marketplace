<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

trait SellerProfileValidationRules
{
    /**
     * Get the validation rules used to register a seller profile.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function sellerProfileRules(): array
    {
        return [
            'seller_type' => ['required', Rule::in(['individual', 'business'])],
            'store_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'pickup_address' => ['required', 'string', 'max:1000'],
            'return_address' => ['required', 'string', 'max:1000'],
            'bank_account_name' => ['required', 'string', 'max:120'],
            'bank_account_details' => ['required', 'string', 'max:1000'],
            'accept_terms' => ['accepted'],
        ];
    }
}
