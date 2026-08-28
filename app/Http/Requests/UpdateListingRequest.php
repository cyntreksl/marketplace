<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListingImages;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateListingRequest extends FormRequest
{
    use ValidatesListingImages;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $sellerProfile = $this->user()?->sellerProfile;
        $listing = $this->route('listing');

        return $sellerProfile !== null
            && $listing instanceof Listing
            && $listing->seller_profile_id === $sellerProfile->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('is_taxonomy_available')->orWhere('is_taxonomy_available', true))
                ->where('is_selectable', true)
                ->whereNull('deleted_at'))],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id', 'prohibits:brand_name'],
            'brand_name' => ['nullable', 'string', 'max:160', 'prohibits:brand_id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'condition' => ['required', Rule::in(['new', 'used', 'refurbished'])],
            'listing_type' => ['required', Rule::in(['buy_now', 'auction'])],
            'location' => ['required', 'string', 'max:120'],
            'warranty' => ['nullable', 'string', 'max:500'],
            'stock_quantity' => ['required_if:listing_type,buy_now', 'nullable', 'integer', 'min:1', 'max:100000'],
            'price' => ['required_if:listing_type,buy_now', 'nullable', 'decimal:0,2', 'min:1'],
            'sale_price' => ['nullable', 'decimal:0,2', 'min:1', 'lt:price', 'prohibited_if:listing_type,auction'],
            'starting_price' => ['required_if:listing_type,auction', 'nullable', 'decimal:0,2', 'min:1'],
            'reserve_price' => ['nullable', 'decimal:0,2', 'gte:starting_price'],
            'minimum_increment' => ['required_if:listing_type,auction', 'nullable', 'decimal:0,2', 'min:1'],
            'starts_at' => ['required_if:listing_type,auction', 'nullable', 'date', 'after_or_equal:now'],
            'ends_at' => ['required_if:listing_type,auction', 'nullable', 'date', 'after:starts_at'],
            ...$this->listingImageRules(required: false),
            'submit_for_review' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareListingImageCrops();
        $brandName = $this->input('brand_name');

        $this->merge([
            'brand_name' => is_string($brandName) && filled($brandName) ? Str::squish($brandName) : null,
        ]);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $listing = $this->route('listing');
            $existingImageCount = $listing instanceof Listing ? $listing->media()->count() : 0;

            $this->validateListingImageCrops($validator, $existingImageCount);
        }];
    }
}
