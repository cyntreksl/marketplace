<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCollectionArtwork;
use App\Models\Collection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCollectionBannerImageRequest extends FormRequest
{
    use ValidatesCollectionArtwork;

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
        return [
            ...$this->collectionArtworkRules('image', 'crop', 1600, 500, true),
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            $this->validateCollectionArtworkRatio('image', 'crop', 16, 5, 'Collection banners must use a 16:5 crop.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCollectionArtworkCrops(['crop']);
    }
}
