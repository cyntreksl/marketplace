<?php

namespace App\Http\Requests;

use App\Models\Guide;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuideRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'supporting_queries' => collect($this->input('supporting_queries', []))->map(fn (mixed $query): string => trim((string) $query))->filter()->values()->all(),
            'remove_hero_image' => $this->boolean('remove_hero_image'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('guide')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $guide = $this->route('guide');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('guides', 'slug')->ignore($guide instanceof Guide ? $guide->getKey() : null)],
            'excerpt' => ['required', 'string', 'max:1000'],
            'quick_answer' => ['required', 'string', 'max:3000'],
            'sections' => ['required', 'array', 'min:1', 'max:20'],
            'sections.*.heading' => ['required', 'string', 'max:255'],
            'sections.*.paragraphs' => ['required', 'array', 'min:1', 'max:12'],
            'sections.*.paragraphs.*' => ['required', 'string', 'max:5000'],
            'buying_checklist' => ['required', 'array', 'min:1', 'max:30'],
            'buying_checklist.*' => ['required', 'string', 'max:500'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
            'remove_hero_image' => ['nullable', 'boolean'],
            'hero_image_alt' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->hasFile('hero_image')
                    || ($guide instanceof Guide && filled($guide->hero_image_path) && ! $this->boolean('remove_hero_image'))),
                'string',
                'max:255',
            ],
            'seo_title' => ['required', 'string', 'max:255'],
            'seo_description' => ['required', 'string', 'max:320'],
            'primary_query' => ['required', 'string', 'max:255'],
            'supporting_queries' => ['nullable', 'array', 'max:20'],
            'supporting_queries.*' => ['required', 'string', 'max:255', 'distinct'],
            'trends_researched_at' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['required', 'array', 'min:1', 'max:12'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')->whereNull('deleted_at')],
        ];
    }
}
