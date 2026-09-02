<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Rules\ValidGtin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesProductData
{
    /** @return array<string, array<int, mixed>> */
    protected function productRules(): array
    {
        $publishing = $this->boolean('submit_for_review');
        $requiredForPublishing = Rule::requiredIf($publishing);
        $listing = $this->route('listing');
        $sellerProfileId = $this->user()?->sellerProfile()->value('id');
        $uniqueSku = Rule::unique('listings', 'sku')->where('seller_profile_id', $sellerProfileId);
        $uniqueBarcode = Rule::unique('listings', 'barcode')->where('seller_profile_id', $sellerProfileId);

        if ($listing instanceof Listing) {
            $uniqueSku->ignore($listing);
            $uniqueBarcode->ignore($listing);
        }

        return [
            'category_id' => [$requiredForPublishing, 'nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('is_taxonomy_available')->orWhere('is_taxonomy_available', true))
                ->where('is_selectable', true)
                ->whereNull('deleted_at'))],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id', 'prohibits:brand_name'],
            'brand_name' => ['nullable', 'string', 'max:160', 'prohibits:brand_id'],
            'sku' => [$requiredForPublishing, 'nullable', 'string', 'max:100', 'regex:/\A[\x21-\x7E]+\z/', $uniqueSku],
            'barcode' => ['nullable', 'string', 'max:100', $uniqueBarcode],
            'gtin' => [Rule::excludeIf($this->input('product_type') === 'variant'), 'nullable', new ValidGtin],
            'mpn' => [Rule::excludeIf($this->input('product_type') === 'variant'), 'nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:160'],
            'title' => [$requiredForPublishing, 'nullable', 'string', 'max:160'],
            'short_description' => ['nullable', 'string', 'max:160'],
            'description' => [$requiredForPublishing, 'nullable', 'string', 'max:10000'],
            'specifications_text' => ['nullable', 'string', 'max:10000'],
            'condition' => [$requiredForPublishing, 'nullable', Rule::in(['new', 'used', 'refurbished'])],
            'product_type' => ['required', Rule::in(['simple', 'variant'])],
            'location' => ['nullable', 'string', 'max:120'],
            'warranty' => ['nullable', 'string', 'max:500'],
            'stock_quantity' => [Rule::requiredIf($publishing && $this->input('product_type') === 'simple'), 'nullable', 'integer', 'min:0', 'max:100000'],
            'selling_price' => [Rule::excludeIf($this->input('product_type') === 'variant'), Rule::requiredIf($publishing), 'nullable', 'decimal:0,2', 'min:1'],
            'compare_price' => [Rule::excludeIf($this->input('product_type') === 'variant'), 'nullable', 'decimal:0,2', 'gt:selling_price'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'allow_backorders' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'is_best_seller' => ['required', 'boolean'],
            'is_new_arrival' => ['required', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'variant_options' => ['nullable', 'array', 'max:3'],
            'variant_options.*.name' => ['nullable', 'string', 'max:80'],
            'variant_options.*.values' => ['nullable', 'array'],
            'variant_options.*.values.*' => ['nullable', 'string', 'max:100'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.selections' => ['required', 'array', 'max:3'],
            'variants.*.sku' => ['nullable', 'string', 'max:100', 'regex:/\A[\x21-\x7E]+\z/'],
            'variants.*.gtin' => [Rule::excludeIf($this->input('product_type') !== 'variant'), 'nullable', new ValidGtin],
            'variants.*.mpn' => [Rule::excludeIf($this->input('product_type') !== 'variant'), 'nullable', 'string', 'max:100'],
            'variants.*.selling_price' => ['nullable', 'decimal:0,2', 'min:1'],
            'variants.*.market_price' => ['nullable', 'decimal:0,2', 'min:1'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'variants.*.image_crop' => ['nullable', 'array'],
            'variants.*.image_crop.x' => ['nullable', 'integer', 'min:0'],
            'variants.*.image_crop.y' => ['nullable', 'integer', 'min:0'],
            'variants.*.image_crop.width' => ['nullable', 'integer', 'min:1'],
            'variants.*.image_crop.height' => ['nullable', 'integer', 'min:1'],
            'variants.*.remove_image' => ['sometimes', 'boolean'],
            'removed_media_ids' => ['nullable', 'array', 'max:5'],
            'removed_media_ids.*' => ['integer', 'distinct'],
            ...$this->listingImageRules(required: false),
            'submit_for_review' => ['required', 'boolean'],
        ];
    }

    protected function prepareProductForValidation(): void
    {
        $this->prepareListingImageCrops();
        $variants = $this->input('variants');

        if (is_array($variants)) {
            $variants = array_map(function (mixed $variant): mixed {
                if (! is_array($variant)) {
                    return $variant;
                }

                $variant['is_active'] = filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL);
                $variant['sku'] = $this->normalizedVariantValue($variant['sku'] ?? null);
                $variant['gtin'] = $this->trimmedVariantValue($variant['gtin'] ?? null);
                $variant['mpn'] = $this->normalizedVariantValue($variant['mpn'] ?? null);

                if (! is_array($variant['image_crop'] ?? null)) {
                    return $variant;
                }

                $variant['image_crop'] = [
                    'x' => (int) ($variant['image_crop']['x'] ?? 0),
                    'y' => (int) ($variant['image_crop']['y'] ?? 0),
                    'width' => (int) ($variant['image_crop']['width'] ?? 0),
                    'height' => (int) ($variant['image_crop']['height'] ?? 0),
                ];

                return $variant;
            }, $variants);
        }

        $sellingPrice = $this->input('selling_price');
        $comparePrice = $this->input('compare_price');

        if (! $this->has('selling_price') && $this->has('price')) {
            $sellingPrice = $this->filled('sale_price') ? $this->input('sale_price') : $this->input('price');
            $comparePrice = $this->filled('sale_price') ? $this->input('price') : null;
        }

        $this->merge([
            'brand_name' => $this->squishedOrNull('brand_name'),
            'sku' => $this->squishedOrNull('sku'),
            'barcode' => $this->squishedOrNull('barcode'),
            'gtin' => $this->trimmedOrNull('gtin'),
            'mpn' => $this->squishedOrNull('mpn'),
            'model' => $this->squishedOrNull('model'),
            'short_description' => $this->squishedOrNull('short_description'),
            'specifications_text' => $this->trimmedOrNull('specifications_text'),
            'meta_title' => $this->squishedOrNull('meta_title'),
            'meta_description' => $this->squishedOrNull('meta_description'),
            'selling_price' => $sellingPrice,
            'compare_price' => $comparePrice,
            'product_type' => $this->input('product_type', 'simple'),
            'low_stock_threshold' => $this->input('low_stock_threshold', 0),
            'allow_backorders' => $this->boolean('allow_backorders'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'is_featured' => $this->boolean('is_featured'),
            'is_best_seller' => $this->boolean('is_best_seller'),
            'is_new_arrival' => $this->boolean('is_new_arrival'),
            'variants' => $variants,
            'submit_for_review' => $this->boolean('submit_for_review'),
        ]);
    }

    /** @return array<int, callable(Validator): void> */
    protected function productAfterValidation(): array
    {
        return [function (Validator $validator): void {
            $listing = $this->route('listing');
            $removedMediaInput = $this->input('removed_media_ids', []);
            $removedMediaIds = collect(is_array($removedMediaInput) ? $removedMediaInput : [])
                ->map(fn (mixed $id): int => (int) $id);
            $existingMediaCount = $listing instanceof Listing
                ? $listing->media()->whereNotIn('id', $removedMediaIds)->count()
                : 0;

            if ($this->has('sale_price') && $validator->errors()->has('compare_price')) {
                $validator->errors()->add('sale_price', $validator->errors()->first('compare_price'));
            }

            $this->validateListingImageCrops($validator, $existingMediaCount);
            $this->validateVariantImageCrops($validator);
            $this->validateSelectedCategory($validator);
            $this->validateRemovedMedia($validator, $listing);
            $this->validateVariantMatrix($validator, $listing);

            if (! $this->boolean('submit_for_review')) {
                return;
            }

            if (! filled($this->input('brand_id')) && ! filled($this->input('brand_name'))) {
                $validator->errors()->add('brand_id', 'Choose a brand or enter a new brand name.');
            }

            $newImages = $this->file('images');
            if ($existingMediaCount + (is_array($newImages) ? count($newImages) : 0) === 0) {
                $validator->errors()->add('images', 'Add at least one product image before submitting for review.');
            }
        }];
    }

    private function validateVariantImageCrops(Validator $validator): void
    {
        $variantsInput = $this->input('variants', []);

        foreach (is_array($variantsInput) ? $variantsInput : [] as $index => $variant) {
            $upload = $this->file("variants.{$index}.image");

            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $crop = Arr::get((array) $variant, 'image_crop');
            $dimensions = $upload->isValid() ? @getimagesize($upload->getPathname()) : false;

            if (! is_array($crop) || $dimensions === false) {
                $validator->errors()->add("variants.{$index}.image_crop", 'Crop and save each variant image before submitting.');

                continue;
            }

            $x = (int) Arr::get($crop, 'x', -1);
            $y = (int) Arr::get($crop, 'y', -1);
            $width = (int) Arr::get($crop, 'width', 0);
            $height = (int) Arr::get($crop, 'height', 0);
            $isSquare = abs($width - $height) <= 2;
            $isInsideImage = $x >= 0
                && $y >= 0
                && $width > 0
                && $height > 0
                && $x + $width <= $dimensions[0]
                && $y + $height <= $dimensions[1];

            if (! $isSquare || ! $isInsideImage) {
                $validator->errors()->add("variants.{$index}.image_crop", 'Variant image crops must be a valid square area inside the uploaded image.');
            }
        }
    }

    private function validateRemovedMedia(Validator $validator, mixed $listing): void
    {
        $removedMediaInput = $this->input('removed_media_ids', []);
        $removedMediaIds = collect(is_array($removedMediaInput) ? $removedMediaInput : [])
            ->map(fn (mixed $id): int => (int) $id);

        if ($removedMediaIds->isEmpty()) {
            return;
        }

        if (! $listing instanceof Listing || $listing->media()->whereKey($removedMediaIds)->count() !== $removedMediaIds->count()) {
            $validator->errors()->add('removed_media_ids', 'One or more selected images do not belong to this product.');
        }
    }

    private function validateSelectedCategory(Validator $validator): void
    {
        if (! filled($this->input('category_id'))) {
            return;
        }

        $isSelectable = Category::query()
            ->whereKey((int) $this->input('category_id'))
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->where(fn ($query) => $query
                ->whereNull('is_taxonomy_available')
                ->orWhere('is_taxonomy_available', true))
            ->exists();

        if (! $isSelectable) {
            $validator->errors()->add('category_id', 'Choose an available leaf category.');
        }
    }

    private function validateVariantMatrix(Validator $validator, mixed $listing): void
    {
        if ($this->input('product_type') !== 'variant') {
            return;
        }

        $variantOptionsInput = $this->input('variant_options', []);
        $options = collect(is_array($variantOptionsInput) ? $variantOptionsInput : [])->map(function (mixed $option): array {
            $values = Arr::get((array) $option, 'values', []);

            return [
                'name' => Str::squish((string) Arr::get((array) $option, 'name', '')),
                'values' => collect(is_array($values) ? $values : [])
                    ->map(fn (mixed $value): string => Str::squish((string) $value))
                    ->filter()
                    ->values(),
            ];
        })->filter(fn (array $option): bool => $option['name'] !== '')->values();

        $optionNames = $options->pluck('name')->map(fn (string $name): string => Str::lower($name));
        if ($optionNames->duplicates()->isNotEmpty()) {
            $validator->errors()->add('variant_options', 'Variant option names must be unique.');
        }

        foreach ($options as $position => $option) {
            $normalizedValues = $option['values']->map(fn (string $value): string => Str::lower($value));
            if ($normalizedValues->duplicates()->isNotEmpty()) {
                $validator->errors()->add("variant_options.{$position}.values", 'Variant values must be unique within an option.');
            }
        }

        $combinationCount = $options->isEmpty() ? 0 : $options->reduce(
            fn (int $total, array $option): int => $total * $option['values']->count(),
            1,
        );

        if ($combinationCount > 100) {
            $validator->errors()->add('variant_options', 'A product can have at most 100 variant combinations.');
        }

        $submittedVariantsInput = $this->input('variants', []);
        $submittedVariants = collect(is_array($submittedVariantsInput) ? $submittedVariantsInput : []);
        if ($combinationCount !== $submittedVariants->count()) {
            $validator->errors()->add('variants', 'Variant rows must exactly match the generated option combinations.');
        }

        $variantSkus = $submittedVariants->pluck('sku')->filter()->map(fn (mixed $sku): string => Str::lower(Str::squish((string) $sku)));
        if ($variantSkus->duplicates()->isNotEmpty()) {
            $validator->errors()->add('variants', 'Variant SKUs must be unique.');
        }

        $baseSku = Str::lower(Str::squish((string) $this->input('sku', '')));
        if ($baseSku !== '' && $variantSkus->contains($baseSku)) {
            $validator->errors()->add('variants', 'Variant SKUs must be different from the base product SKU.');
        }

        $variantGtins = $submittedVariants->pluck('gtin')->filter();
        if ($variantGtins->duplicates()->isNotEmpty()) {
            $validator->errors()->add('variants', 'Variant GTINs must be unique within a product.');
        }

        if ($this->boolean('submit_for_review')) {
            if ($options->isEmpty() || $options->contains(fn (array $option): bool => $option['values']->isEmpty())) {
                $validator->errors()->add('variant_options', 'Variant products need at least one complete option group.');
            }

            if ($submittedVariants->contains(fn (mixed $variant): bool => ! filled(Arr::get((array) $variant, 'sku')))) {
                $validator->errors()->add('variants', 'Every variant needs a SKU before submitting for review.');
            }

            $activeVariants = $submittedVariants->filter(
                fn (mixed $variant): bool => filter_var(Arr::get((array) $variant, 'is_active', true), FILTER_VALIDATE_BOOL),
            );

            if ($activeVariants->isEmpty()) {
                $validator->errors()->add('variants', 'At least one variant must be active before submitting for review.');
            }

            foreach ($activeVariants as $index => $variant) {
                if (! filled(Arr::get((array) $variant, 'selling_price'))) {
                    $validator->errors()->add("variants.{$index}.selling_price", 'Enter a selling price for each active variant.');
                }
            }
        }

        foreach ($submittedVariants as $index => $variant) {
            $sellingPrice = Arr::get((array) $variant, 'selling_price');
            $marketPrice = Arr::get((array) $variant, 'market_price');

            if (filled($sellingPrice) && filled($marketPrice) && (float) $marketPrice <= (float) $sellingPrice) {
                $validator->errors()->add("variants.{$index}.market_price", 'The market price must be greater than the selling price.');
            }
        }

        $sellerProfileId = $this->user()?->sellerProfile()->value('id');
        $listingId = $listing instanceof Listing ? $listing->id : null;
        $conflictingSku = ListingVariant::query()
            ->where('seller_profile_id', $sellerProfileId)
            ->when($listingId, fn ($query, int $id) => $query->where('listing_id', '!=', $id))
            ->whereIn('sku', $submittedVariants->pluck('sku')->filter())
            ->exists();

        if ($conflictingSku) {
            $validator->errors()->add('variants', 'One or more variant SKUs are already used by another product.');
        }

        $variantSkuConflictsWithProduct = Listing::query()
            ->where('seller_profile_id', $sellerProfileId)
            ->when($listingId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->whereIn('sku', $submittedVariants->pluck('sku')->filter())
            ->exists();

        if ($variantSkuConflictsWithProduct) {
            $validator->errors()->add('variants', 'One or more variant SKUs are already used by another product.');
        }

        $baseSkuConflictsWithVariant = $baseSku !== '' && ListingVariant::query()
            ->where('seller_profile_id', $sellerProfileId)
            ->when($listingId, fn ($query, int $id) => $query->where('listing_id', '!=', $id))
            ->where('sku', $this->input('sku'))
            ->exists();

        if ($baseSkuConflictsWithVariant) {
            $validator->errors()->add('sku', 'The SKU is already used by a variant.');
        }
    }

    private function squishedOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && filled($value) ? Str::squish($value) : null;
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && filled($value) ? trim($value) : null;
    }

    private function normalizedVariantValue(mixed $value): ?string
    {
        return is_string($value) && filled($value) ? Str::squish($value) : null;
    }

    private function trimmedVariantValue(mixed $value): ?string
    {
        return is_string($value) && filled($value) ? trim($value) : null;
    }
}
