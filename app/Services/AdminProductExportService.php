<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\ListingVariant;
use Illuminate\Support\Collection;

class AdminProductExportService
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'product_id' => 'Product ID',
        'variant_id' => 'Variant ID',
        'title' => 'Title',
        'variant' => 'Variant',
        'sku' => 'SKU',
        'gtin' => 'GTIN',
        'mpn' => 'MPN',
        'barcode' => 'Barcode',
        'status' => 'Status',
        'listing_type' => 'Selling format',
        'product_type' => 'Product type',
        'condition' => 'Condition',
        'seller' => 'Seller',
        'category' => 'Category',
        'brand' => 'Brand',
        'location' => 'Location',
        'retail_enabled' => 'Retail enabled',
        'wholesale_enabled' => 'Wholesale enabled',
        'selling_price' => 'Selling price',
        'compare_at_price' => 'Compare-at price',
        'wholesale_price' => 'Wholesale price',
        'wholesale_min_quantity' => 'Wholesale minimum quantity',
        'cost_price' => 'Cost price',
        'supplier_name' => 'Supplier name',
        'internal_notes' => 'Internal notes',
        'stock_quantity' => 'Stock quantity',
        'reserved_quantity' => 'Reserved quantity',
        'available_quantity' => 'Available quantity',
        'active' => 'Active',
        'created_at' => 'Created date',
        'updated_at' => 'Updated date',
    ];

    /** @var list<string> */
    private const DEFAULT_COLUMNS = [
        'product_id', 'title', 'sku', 'status', 'seller', 'category', 'selling_price', 'available_quantity', 'created_at',
    ];

    public function __construct(
        private readonly ListingRepository $listings,
        private readonly AdminXlsxWriter $writer,
    ) {}

    /** @return list<string> */
    public static function columnKeys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /** @return list<array{key: string, label: string, defaultSelected: bool}> */
    public static function columnOptions(): array
    {
        $options = [];

        foreach (self::COLUMNS as $key => $label) {
            $options[] = [
                'key' => $key,
                'label' => $label,
                'defaultSelected' => in_array($key, self::DEFAULT_COLUMNS, true),
            ];
        }

        return $options;
    }

    /** @param array<string, mixed> $filters
     * @param  list<string>  $columns
     */
    public function createTemporaryFile(array $filters, array $columns): string
    {
        $headers = array_map(fn (string $column): string => self::COLUMNS[$column], $columns);

        return $this->writer->createTemporaryFile('Products', $headers, $this->rows($filters, $columns));
    }

    /** @param array<string, mixed> $filters
     * @param  list<string>  $columns
     * @return \Generator<int, list<mixed>>
     */
    private function rows(array $filters, array $columns): \Generator
    {
        foreach ($this->listings->lazyForAdminExport($filters) as $listing) {
            $listing->makeVisible(['cost_price', 'supplier_name', 'internal_notes']);

            if ($listing->product_type !== 'variant') {
                yield $this->row($listing, null, $columns);

                continue;
            }

            $activeVariants = $listing->variants
                ->where('is_active', true)
                ->sortBy([
                    ['position', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
            if ($activeVariants->isEmpty()) {
                yield $this->row($listing, null, $columns);

                continue;
            }

            foreach ($activeVariants as $variant) {
                $variant->makeVisible('cost_price');
                yield $this->row($listing, $variant, $columns);
            }
        }
    }

    /** @param list<string> $columns
     * @return list<mixed>
     */
    private function row(Listing $listing, ?ListingVariant $variant, array $columns): array
    {
        return array_map(
            fn (string $column): mixed => $this->value($listing, $variant, $column),
            $columns,
        );
    }

    private function value(Listing $listing, ?ListingVariant $variant, string $column): mixed
    {
        $isVariantFallback = $listing->product_type === 'variant' && $variant === null;

        return match ($column) {
            'product_id' => $listing->id,
            'variant_id' => $variant?->id,
            'title' => $listing->title,
            'variant' => $variant === null ? null : $this->variantLabel($variant->optionValues),
            'sku' => $isVariantFallback ? null : ($variant === null ? $listing->sku : $variant->sku),
            'gtin' => $isVariantFallback ? null : ($variant === null ? $listing->gtin : $variant->gtin),
            'mpn' => $isVariantFallback ? null : ($variant === null ? $listing->mpn : $variant->mpn),
            'barcode' => $listing->barcode,
            'status' => $listing->status,
            'listing_type' => $listing->listing_type,
            'product_type' => $listing->product_type,
            'condition' => $listing->condition,
            'seller' => $listing->sellerProfile?->store_name,
            'category' => $listing->category?->name,
            'brand' => $listing->brand === null ? $listing->brand_name : $listing->brand->name,
            'location' => $listing->location,
            'retail_enabled' => (bool) $listing->is_retail_enabled,
            'wholesale_enabled' => (bool) $listing->is_wholesale_enabled,
            'selling_price' => $isVariantFallback ? null : $this->sellingPrice($listing, $variant),
            'compare_at_price' => $isVariantFallback ? null : $this->compareAtPrice($listing, $variant),
            'wholesale_price' => $isVariantFallback ? null : $this->number($variant === null ? $listing->wholesale_price : $variant->wholesale_price),
            'wholesale_min_quantity' => $isVariantFallback ? null : ($variant === null ? $listing->wholesale_min_quantity : $variant->wholesale_min_quantity),
            'cost_price' => $isVariantFallback ? null : $this->number($variant === null ? $listing->getAttribute('cost_price') : $variant->getAttribute('cost_price')),
            'supplier_name' => $listing->getAttribute('supplier_name'),
            'internal_notes' => $listing->getAttribute('internal_notes'),
            'stock_quantity' => $isVariantFallback ? null : ($variant === null ? $listing->stock_quantity : $variant->stock_quantity),
            'reserved_quantity' => $isVariantFallback ? null : ($variant === null ? $listing->reserved_quantity : $variant->reserved_quantity),
            'available_quantity' => $isVariantFallback ? null : ($variant === null ? max(0, $listing->stock_quantity - $listing->reserved_quantity) : $variant->availableQuantity()),
            'active' => $isVariantFallback ? null : ($variant === null ? (bool) $listing->is_active : $variant->is_active),
            'created_at' => $listing->created_at,
            'updated_at' => $listing->updated_at,
            default => null,
        };
    }

    private function compareAtPrice(Listing $listing, ?ListingVariant $variant): ?float
    {
        if ($variant !== null) {
            return $variant->market_price !== null && (float) $variant->market_price > (float) $variant->selling_price
                ? (float) $variant->market_price
                : null;
        }

        return $listing->price !== null && $listing->sale_price !== null && (float) $listing->sale_price < (float) $listing->price
            ? (float) $listing->price
            : null;
    }

    private function sellingPrice(Listing $listing, ?ListingVariant $variant): ?float
    {
        if ($variant !== null) {
            return $this->number($variant->selling_price);
        }

        $price = $listing->price;
        if ($price !== null && $listing->sale_price !== null && (float) $listing->sale_price < (float) $price) {
            return (float) $listing->sale_price;
        }

        return $this->number($price);
    }

    /** @param Collection<int, mixed> $optionValues */
    private function variantLabel(Collection $optionValues): ?string
    {
        $label = $optionValues
            ->sortBy(fn ($value): int => (int) $value->option->position)
            ->map(fn ($value): string => $value->option->name.': '.$value->value)
            ->implode(' / ');

        return $label === '' ? null : $label;
    }

    private function number(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
