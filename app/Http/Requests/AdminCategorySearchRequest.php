<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCategorySearchRequest extends FormRequest
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
            'query' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'all',
                'storefront_visible',
                'admin_active',
                'admin_inactive',
                'taxonomy_unavailable',
                'archived',
            ])],
            'parent_options' => ['nullable', 'boolean'],
            'exclude_subtree_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }
}
