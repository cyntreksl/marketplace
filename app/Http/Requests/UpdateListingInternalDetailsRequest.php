<?php

namespace App\Http\Requests;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingInternalDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            && $listing->seller_profile_id === $this->user()?->sellerProfile?->id
            && $listing->status !== 'archived';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $listing = $this->route('listing');
        $isVariant = $listing instanceof Listing && $listing->product_type === 'variant';

        return [
            'supplier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'cost_price' => [Rule::prohibitedIf($isVariant), 'sometimes', 'nullable', 'decimal:0,2', 'between:0,9999999999.99'],
            'variants' => [Rule::prohibitedIf(! $isVariant), 'sometimes', 'array', 'max:100'],
            'variants.*' => ['array:id,cost_price'],
            'variants.*.id' => ['required', 'integer', 'distinct'],
            'variants.*.cost_price' => ['present', 'nullable', 'decimal:0,2', 'between:0,9999999999.99'],
        ];
    }
}
