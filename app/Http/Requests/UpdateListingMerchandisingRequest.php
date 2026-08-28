<?php

namespace App\Http\Requests;

use App\Models\Listing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateListingMerchandisingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && ($this->user()?->can('moderate', $listing) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_best_offer' => ['required', 'boolean'],
            'is_new_arrival' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $listing = $this->route('listing');

            if (($this->boolean('is_best_offer') || $this->boolean('is_new_arrival'))
                && (! $listing instanceof Listing || $listing->status !== 'approved')) {
                $validator->errors()->add('is_new_arrival', 'Only approved listings can be featured on the homepage.');
            }

            if ($this->boolean('is_best_offer') && (! $listing instanceof Listing
                || $listing->status !== 'approved'
                || $listing->listing_type !== 'buy_now'
                || $listing->sale_price === null
                || (float) $listing->sale_price >= (float) $listing->price)) {
                $validator->errors()->add('is_best_offer', 'Best Offers must be approved buy-now listings with a lower sale price.');
            }
        }];
    }
}
