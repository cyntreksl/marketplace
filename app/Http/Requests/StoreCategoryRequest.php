<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategoryArtwork;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    use ValidatesCategoryArtwork;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'google_product_category_id' => ['nullable', 'integer', 'min:1', Rule::unique('categories', 'google_product_category_id')],
            'name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')],
            'commission_percentage' => ['required', 'numeric', 'between:0,100'], 'return_window_days' => ['required', 'integer', 'min:0', 'max:365'],
            'cod_enabled' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
            ...$this->categoryArtworkRules('image', 'image_crop', 800, 800, false),
            ...$this->categoryArtworkRules('banner_image', 'banner_image_crop', 900, 1200, false),
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            $this->validateCategoryArtworkRatio('image', 'image_crop', 1, 1, 'Category images must use a 1:1 crop.'),
            $this->validateCategoryArtworkRatio('banner_image', 'banner_image_crop', 3, 4, 'Category banners must use a 3:4 crop.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCategoryArtworkCrops(['image_crop', 'banner_image_crop']);
    }
}
