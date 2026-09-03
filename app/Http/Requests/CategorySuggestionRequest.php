<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategorySuggestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:8'],
            'top_path' => ['nullable', 'string', 'max:160'],
            'current_parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('is_taxonomy_available')->orWhere('is_taxonomy_available', true))
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title', $this->input('text'));

        $this->merge([
            'title' => is_string($title) ? Str::squish($title) : $title,
            'top_path' => is_string($this->input('top_path')) ? Str::squish($this->input('top_path')) : $this->input('top_path'),
        ]);
    }
}
