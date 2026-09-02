<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesListingImages
{
    /** @return array<string, array<int, mixed>> */
    protected function listingImageRules(bool $required): array
    {
        return [
            'images' => [$required ? 'required' : 'nullable', 'array', $required ? 'min:1' : 'min:0', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_crops' => [$required ? 'required' : 'required_with:images', 'array', $required ? 'min:1' : 'min:0', 'max:5'],
            'image_crops.*.x' => ['required', 'integer', 'min:0'],
            'image_crops.*.y' => ['required', 'integer', 'min:0'],
            'image_crops.*.width' => ['required', 'integer', 'min:1'],
            'image_crops.*.height' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareListingImageCrops(): void
    {
        $crops = $this->input('image_crops');

        if (! is_array($crops)) {
            return;
        }

        $this->merge([
            'image_crops' => array_map(fn (mixed $crop): mixed => is_array($crop) ? [
                'x' => (int) ($crop['x'] ?? 0),
                'y' => (int) ($crop['y'] ?? 0),
                'width' => (int) ($crop['width'] ?? 0),
                'height' => (int) ($crop['height'] ?? 0),
            ] : $crop, $crops),
        ]);
    }

    protected function validateListingImageCrops(Validator $validator, int $existingImageCount = 0): void
    {
        $uploads = $this->file('images');
        $images = is_array($uploads) ? array_values($uploads) : [];
        $crops = $this->input('image_crops', []);
        $crops = is_array($crops) ? array_values($crops) : [];

        if (count($images) !== count($crops)) {
            $validator->errors()->add('image_crops', 'Every new photo must have crop information.');
        }

        if ($existingImageCount + count($images) > 5) {
            $validator->errors()->add('images', 'A listing can have a maximum of five photos.');
        }

        foreach ($crops as $index => $crop) {
            if (! is_array($crop)) {
                continue;
            }

            $width = (int) ($crop['width'] ?? 0);
            $height = (int) ($crop['height'] ?? 0);

            if ($width > 0 && $height > 0 && abs($width - $height) > 2) {
                $validator->errors()->add("image_crops.{$index}.width", 'Photo crops must use a square 1:1 aspect ratio.');
            }
        }
    }
}
