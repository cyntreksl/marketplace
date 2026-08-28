<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesCategoryArtwork
{
    /** @return array<string, array<int, mixed>> */
    protected function categoryArtworkRules(
        string $imageField,
        string $cropField,
        int $minimumWidth,
        int $minimumHeight,
        bool $required,
    ): array {
        return [
            $imageField => [
                $required ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                Rule::dimensions()
                    ->minWidth($minimumWidth)
                    ->minHeight($minimumHeight)
                    ->maxWidth(6000)
                    ->maxHeight(6000),
            ],
            $cropField => [$required ? 'required' : "required_with:{$imageField}", 'array'],
            "{$cropField}.x" => [$required ? 'required' : "required_with:{$imageField}", 'integer', 'min:0', 'max:6000'],
            "{$cropField}.y" => [$required ? 'required' : "required_with:{$imageField}", 'integer', 'min:0', 'max:6000'],
            "{$cropField}.width" => [$required ? 'required' : "required_with:{$imageField}", 'integer', "min:{$minimumWidth}", 'max:6000'],
            "{$cropField}.height" => [$required ? 'required' : "required_with:{$imageField}", 'integer', "min:{$minimumHeight}", 'max:6000'],
        ];
    }

    /** @param array<int, string> $cropFields */
    protected function prepareCategoryArtworkCrops(array $cropFields): void
    {
        foreach ($cropFields as $cropField) {
            $crop = $this->input($cropField);

            if (! is_array($crop)) {
                continue;
            }

            foreach (['x', 'y', 'width', 'height'] as $coordinate) {
                if (isset($crop[$coordinate]) && is_numeric($crop[$coordinate])) {
                    $crop[$coordinate] = (int) $crop[$coordinate];
                }
            }

            $this->merge([$cropField => $crop]);
        }
    }

    protected function validateCategoryArtworkRatio(
        string $imageField,
        string $cropField,
        int $ratioWidth,
        int $ratioHeight,
        string $message,
    ): Closure {
        return function (Validator $validator) use ($imageField, $cropField, $ratioWidth, $ratioHeight, $message): void {
            if (! $this->hasFile($imageField)) {
                return;
            }

            $crop = $this->input($cropField);

            if (! is_array($crop)) {
                return;
            }

            $width = (int) ($crop['width'] ?? 0);
            $height = (int) ($crop['height'] ?? 0);
            $difference = abs(($width * $ratioHeight) - ($height * $ratioWidth));
            $tolerance = max($ratioWidth, $ratioHeight) * 2;

            if ($width > 0 && $height > 0 && $difference > $tolerance) {
                $validator->errors()->add($cropField, $message);
            }
        };
    }
}
