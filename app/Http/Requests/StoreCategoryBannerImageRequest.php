<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategoryArtwork;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCategoryBannerImageRequest extends FormRequest
{
    use ValidatesCategoryArtwork;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->categoryArtworkRules('image', 'crop', 900, 1200, true),
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            $this->validateCategoryArtworkRatio('image', 'crop', 3, 4, 'Category banners must use a 3:4 crop.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCategoryArtworkCrops(['crop']);
    }
}
