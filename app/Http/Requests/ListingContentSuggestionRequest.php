<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListingContentSuggestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'target' => ['required', Rule::in(['seo', 'short_description', 'specifications'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? Str::squish($this->input('title')) : $this->input('title'),
            'description' => is_string($this->input('description')) ? Str::squish(strip_tags($this->input('description'))) : $this->input('description'),
        ]);
    }
}
