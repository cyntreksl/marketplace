<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePromotionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'visual_theme' => $this->input('visual_theme', $this->route('promotion')?->visual_theme ?? 'orange'),
            'listing_ids' => $this->input('listing_ids', $this->route('promotion')?->listings()->pluck('listings.id')->all() ?? []),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion && ($this->user()?->can('update', $promotion) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'visual_theme' => ['required', Rule::in(['orange', 'dark', 'light'])],
            'artwork_alt' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120'],
            'link_url' => ['nullable', 'string', 'max:1000', 'regex:/^\/(?!\/)/'],
            'placement' => ['required', Rule::in(['hero', 'secondary', 'flash_sale'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'required_if:placement,flash_sale', 'date', 'after:starts_at'],
            'listing_ids' => ['present', 'array', 'max:12'],
            'listing_ids.*' => ['integer', 'distinct', Rule::exists('listings', 'id')->where(fn ($query) => $query->where('status', 'approved')->where('is_active', true)->where('listing_type', 'buy_now'))],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $promotion = $this->route('promotion');

            if ($this->input('placement') === 'flash_sale' && count($this->input('listing_ids', [])) === 0) {
                $validator->errors()->add('listing_ids', 'A flash sale needs at least one approved listing.');
            }

            if ($this->boolean('is_active') && $this->input('placement') === 'hero'
                && Promotion::query()->where('placement', 'hero')->where('is_active', true)->whereKeyNot($promotion?->getKey())->count() >= 5) {
                $validator->errors()->add('placement', 'Only five active hero slides are allowed.');
            }
        }];
    }
}
