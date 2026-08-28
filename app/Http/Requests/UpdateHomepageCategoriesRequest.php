<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomepageCategoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Category::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'popular_category_ids' => ['present', 'array', 'max:10'],
            'popular_category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('is_taxonomy_available')->orWhere('is_taxonomy_available', true))
                ->whereNull('deleted_at'))],
            'featured_category_ids' => ['present', 'array', 'max:5'],
            'featured_category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('is_taxonomy_available')->orWhere('is_taxonomy_available', true))
                ->whereNull('deleted_at'))],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
