<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontBrowseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'listing_type' => ['nullable', Rule::in(['buy_now', 'auction'])],
            'location' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{search?: string|null, category?: string|null, brand?: string|null, condition?: string|null, listing_type?: string|null, location?: string|null, min_price?: int|float|string|null, max_price?: int|float|string|null, sort: string}
     */
    public function filters(): array
    {
        $filters = $this->safe()->only([
            'search',
            'category',
            'brand',
            'condition',
            'listing_type',
            'location',
            'min_price',
            'max_price',
            'sort',
        ]);

        $filters['sort'] = $filters['sort'] ?? 'newest';

        return $filters;
    }
}
