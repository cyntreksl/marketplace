<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $rules = ['about' => ['nullable', 'string', 'max:2000']];
        foreach (['logo' => [128, 128], 'cover' => [800, 200]] as $field => [$width, $height]) {
            $rules[$field] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', "dimensions:min_width={$width},min_height={$height},max_width=6000,max_height=6000"];
            $rules['remove_'.$field] = ['sometimes', 'boolean'];
            $rules[$field.'_crop'] = ['required_with:'.$field, 'array:x,y,width,height'];
            foreach (['x', 'y', 'width', 'height'] as $dimension) {
                $minimum = in_array($dimension, ['x', 'y']) ? 0 : 1;
                $rules[$field.'_crop.'.$dimension] = ['required_with:'.$field, 'integer', 'min:'.$minimum, 'max:6000'];
            }
        }

        return $rules;
    }
}
