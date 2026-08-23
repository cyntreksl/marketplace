<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
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
