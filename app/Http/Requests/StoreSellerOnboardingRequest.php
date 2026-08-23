<?php

namespace App\Http\Requests;

use App\Concerns\SellerProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSellerOnboardingRequest extends FormRequest
{
    use SellerProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->sellerProfileRules();
    }
}
