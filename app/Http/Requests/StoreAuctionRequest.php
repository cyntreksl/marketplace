<?php

namespace App\Http\Requests;

use App\AuctionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuctionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->sellerProfile()->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'listing_variant_id' => ['nullable', 'integer', 'exists:listing_variants,id'],
            'type' => ['required', Rule::enum(AuctionType::class)],
            'quantity' => ['required', 'integer', 'between:1,100000'],
            'starting_price' => ['required', 'decimal:0,2', 'min:1'],
            'minimum_increment' => ['required', 'decimal:0,2', 'min:1'],
            'extension_window_minutes' => ['nullable', 'required_if:type,time_extended', 'integer', 'between:1,60'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
