<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'listing_variant_id' => ['nullable', 'integer', 'exists:listing_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'buy_now' => ['sometimes', 'boolean'],
        ];
    }
}
