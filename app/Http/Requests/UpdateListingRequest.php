<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListingImages;
use App\Http\Requests\Concerns\ValidatesProductData;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateListingRequest extends FormRequest
{
    use ValidatesListingImages;
    use ValidatesProductData;

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
        return $this->productRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareProductForValidation();
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return $this->productAfterValidation();
    }
}
