<?php

namespace App\Services;

use App\Contracts\Repositories\ListingVariantRepository;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListingVariantService
{
    public function __construct(
        private readonly ListingVariantRepository $variants,
        private readonly ListingImageService $images,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function synchronize(Listing $listing, array $attributes): void
    {
        if (($attributes['product_type'] ?? 'simple') !== 'variant') {
            $this->images->removeVariantImages($this->variants->imagesExcept($listing, []));
            $this->variants->deleteForListing($listing);

            return;
        }

        $variantOptions = Arr::get($attributes, 'variant_options', []);
        $variantRows = Arr::get($attributes, 'variants', []);
        $options = $this->normalizedOptions(is_array($variantOptions) ? $variantOptions : []);
        $submittedVariants = collect(is_array($variantRows) ? $variantRows : [])->keyBy(
            fn (array $variant): string => $this->combinationKey($variant['selections'] ?? [], $options),
        );
        $matrix = $this->combinations($options);
        $expectedKeys = collect($matrix)->map(fn (array $selections): string => $this->combinationKey($selections, $options))->sort()->values();

        if (count($matrix) > 100) {
            throw ValidationException::withMessages(['variant_options' => 'A product can have at most 100 variant combinations.']);
        }

        $variants = collect($matrix)->map(function (array $selections) use ($attributes, $options, $submittedVariants): array {
            $key = $this->combinationKey($selections, $options);
            $submitted = $submittedVariants->get($key, []);

            return [
                'combination_key' => $key,
                'selections' => $selections,
                'sku' => filled($submitted['sku'] ?? null)
                    ? Str::squish((string) $submitted['sku'])
                    : $this->suggestedSku((string) ($attributes['sku'] ?? ''), $selections),
                'selling_price' => filled($submitted['selling_price'] ?? null) ? $submitted['selling_price'] : null,
                'market_price' => filled($submitted['market_price'] ?? null) ? $submitted['market_price'] : null,
                'stock_quantity' => max(0, (int) ($submitted['stock_quantity'] ?? 0)),
                'is_active' => filter_var($submitted['is_active'] ?? true, FILTER_VALIDATE_BOOL),
            ];
        })->all();

        if ($submittedVariants->keys()->sort()->values()->all() !== $expectedKeys->all()) {
            throw ValidationException::withMessages(['variants' => 'Variant rows must exactly match the generated option combinations.']);
        }

        $this->images->removeVariantImages($this->variants->imagesExcept($listing, $expectedKeys->all()));
        $synchronizedVariants = $this->variants->replaceForListing($listing, $options, $variants);

        foreach ($synchronizedVariants as $variant) {
            $submitted = $submittedVariants->get($variant->combination_key, []);
            $upload = $submitted['image'] ?? null;
            $crop = $submitted['image_crop'] ?? null;
            $existingImage = $variant->image()->first();

            if ($upload instanceof UploadedFile) {
                if (! is_array($crop)) {
                    throw ValidationException::withMessages([
                        'variants' => 'Crop and save each variant image before submitting.',
                    ]);
                }

                $resolvedCrop = [
                    'x' => (int) Arr::get($crop, 'x', 0),
                    'y' => (int) Arr::get($crop, 'y', 0),
                    'width' => (int) Arr::get($crop, 'width', 0),
                    'height' => (int) Arr::get($crop, 'height', 0),
                ];

                if ($existingImage !== null) {
                    $this->images->removeVariantImages(collect([$existingImage]));
                }

                $this->images->storeVariant($listing, $variant, $upload, $resolvedCrop);

                continue;
            }

            if (($submitted['remove_image'] ?? false) && $existingImage !== null) {
                $this->images->removeVariantImages(collect([$existingImage]));
            }
        }
    }

    /** @param array<int, mixed> $options
     * @return array<int, array{name: string, values: array<int, string>}>
     */
    public function normalizedOptions(array $options): array
    {
        return collect($options)
            ->take(3)
            ->map(function (mixed $option): array {
                $values = Arr::get((array) $option, 'values', []);

                return [
                    'name' => Str::squish((string) Arr::get((array) $option, 'name', '')),
                    'values' => collect(is_array($values) ? $values : [])
                        ->map(fn (mixed $value): string => Str::squish((string) $value))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $option): bool => $option['name'] !== '')
            ->values()
            ->all();
    }

    /** @param array<int, array{name: string, values: array<int, string>}> $options
     * @return array<int, array<int, string>>
     */
    public function combinations(array $options): array
    {
        if ($options === [] || collect($options)->contains(fn (array $option): bool => $option['values'] === [])) {
            return [];
        }

        return collect($options)->reduce(function (array $combinations, array $option, int $position): array {
            return collect($combinations)->flatMap(fn (array $combination): array => collect($option['values'])
                ->map(fn (string $value): array => [...$combination, $position => $value])
                ->all())->all();
        }, [[]]);
    }

    /** @param array<int|string, mixed> $selections
     * @param  array<int, array{name: string, values: array<int, string>}>  $options
     */
    public function combinationKey(array $selections, array $options = []): string
    {
        $normalized = collect($selections)
            ->sortKeys()
            ->map(fn (mixed $value, int|string $position): string => Str::lower(
                Str::squish((string) ($options[(int) $position]['name'] ?? $position)).':'.Str::squish((string) $value),
            ))
            ->values()
            ->implode('|');

        return hash('sha256', $normalized);
    }

    /** @param array<int, string> $selections */
    private function suggestedSku(string $baseSku, array $selections): string
    {
        $parts = collect([$baseSku !== '' ? $baseSku : 'PRODUCT', ...$selections])
            ->map(fn (string $part): string => Str::upper(Str::slug($part)))
            ->filter();

        return Str::limit($parts->implode('-'), 100, '');
    }
}
