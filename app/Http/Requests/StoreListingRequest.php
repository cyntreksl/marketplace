<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'condition' => ['required', Rule::in(['new', 'used', 'refurbished'])],
            'listing_type' => ['required', Rule::in(['buy_now', 'auction'])],
            'location' => ['required', 'string', 'max:120'],
            'warranty' => ['nullable', 'string', 'max:500'],
            'stock_quantity' => ['required_if:listing_type,buy_now', 'nullable', 'integer', 'min:1', 'max:100000'],
            'price' => ['required_if:listing_type,buy_now', 'nullable', 'decimal:0,2', 'min:1'],
            'starting_price' => ['required_if:listing_type,auction', 'nullable', 'decimal:0,2', 'min:1'],
            'reserve_price' => ['nullable', 'decimal:0,2', 'gte:starting_price'],
            'minimum_increment' => ['required_if:listing_type,auction', 'nullable', 'decimal:0,2', 'min:1'],
            'starts_at' => ['required_if:listing_type,auction', 'nullable', 'date', 'after_or_equal:now'],
            'ends_at' => ['required_if:listing_type,auction', 'nullable', 'date', 'after:starts_at'],
        ];
    }
}
