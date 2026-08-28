<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

trait SellerProfileValidationRules
{
    /**
     * Get the validation rules used to collect seller contact and store details.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function sellerRegistrationRules(): array
    {
        return [
            'seller_type' => ['required', Rule::in(['individual', 'business'])],
            'store_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * Get the validation rules used to complete a seller profile.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function sellerProfileRules(): array
    {
        return [
            ...$this->sellerRegistrationRules(),
            'bank_account_name' => ['required', 'string', 'max:120'],
            'bank_account_details' => ['required', 'string', 'max:1000'],
        ];
    }
}
