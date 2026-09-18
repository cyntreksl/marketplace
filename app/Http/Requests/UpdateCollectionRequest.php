<?php

namespace App\Http\Requests;

use App\Models\Collection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $collection = $this->route('collection');

        return $collection instanceof Collection && ($this->user()?->can('update', $collection) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Collection $collection */
        $collection = $this->route('collection');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('collections', 'slug')->ignore($collection->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'show_on_homepage_tile' => ['required', 'boolean'],
            'show_on_homepage_grid' => ['required', 'boolean'],
            'show_in_navigation' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_intro' => ['nullable', 'string', 'max:5000'],
            'listing_ids' => [$collection->isManual() ? 'required' : 'nullable', 'array', 'max:200'],
            'listing_ids.*' => ['integer', 'distinct', Rule::exists('listings', 'id')],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
