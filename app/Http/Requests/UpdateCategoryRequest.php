<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at'), Rule::notIn([$this->route('category')->id])],
            'google_product_category_id' => ['nullable', 'integer', 'min:1', Rule::unique('categories', 'google_product_category_id')->ignore($this->route('category'))],
            'name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            'commission_percentage' => ['required', 'numeric', 'between:0,100'], 'return_window_days' => ['required', 'integer', 'min:0', 'max:365'],
            'cod_enabled' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
