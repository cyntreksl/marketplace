<?php

namespace App\Http\Requests;

use App\Models\SeoRedirect;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeoRedirectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_path' => $this->normalizePath($this->input('source_path')),
            'destination_path' => $this->normalizePath($this->input('destination_path')),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', SeoRedirect::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_path' => ['required', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s?#]*$/u', Rule::unique('seo_redirects', 'source_path')],
            'destination_path' => ['required', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s?#]*$/u'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function normalizePath(mixed $value): string
    {
        $path = trim((string) $value);

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $path;
        }

        return $path === '/' ? '/' : '/'.trim($path, '/');
    }
}
