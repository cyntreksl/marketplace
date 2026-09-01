<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesListingImages;
use App\Http\Requests\Concerns\ValidatesProductData;
use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreListingRequest extends FormRequest
{
    use ValidatesListingImages;
    use ValidatesProductData;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Listing::class) ?? false;
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
