<?php

namespace App\Http\Requests;

use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_featured' => $this->input('is_featured', false)]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('brands', 'slug')],
            'logo' => ['nullable', 'image', 'max:2048'],
            'is_featured' => ['required', 'boolean'],
            'homepage_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
