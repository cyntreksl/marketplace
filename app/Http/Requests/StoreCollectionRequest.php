<?php

namespace App\Http\Requests;

use App\Models\Collection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Collection::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('collections', 'slug')],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['manual'])],
            'is_active' => ['required', 'boolean'],
            'show_on_homepage_tile' => ['required', 'boolean'],
            'show_on_homepage_grid' => ['required', 'boolean'],
            'show_in_navigation' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_intro' => ['nullable', 'string', 'max:5000'],
            'listing_ids' => ['required', 'array', 'max:200'],
            'listing_ids.*' => ['integer', 'distinct', Rule::exists('listings', 'id')],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === 'rule') {
                    $validator->errors()->add('type', 'New rule-based collections cannot be created from the admin panel.');
                }
            },
        ];
    }
}
