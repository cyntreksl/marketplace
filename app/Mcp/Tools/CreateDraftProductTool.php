<?php

namespace App\Mcp\Tools;

use App\Models\Listing;
use App\Models\User;
use App\Services\ListingService;
use App\Services\ListingVariantService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-draft-product')]
#[Title('Create Draft Product')]
#[Description('Creates a new draft product listing for a seller in the marketplace. Supports both simple products and variant products (e.g. options for Size, Color). Automatically ensures SEO metadata (meta_title, meta_description) is populated. The product is strictly saved in draft status and is never published or submitted for review.')]
class CreateDraftProductTool extends Tool
{
    public function __construct(
        private readonly ListingService $listings,
        private readonly ListingVariantService $variantService,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $seller = $this->resolveSeller($request);

        if (! $seller instanceof User) {
            return Response::error('Unable to identify seller. Please provide a valid "seller_email" or "seller_id", or authenticate the session.');
        }

        if (! $seller->sellerProfile()->exists()) {
            return Response::error("User [{$seller->email}] does not have an associated seller profile.");
        }

        $attributes = $this->prepareAttributes($request);

        try {
            $listing = $this->listings->createDraft($seller, $attributes);
        } catch (ValidationException $e) {
            $messages = collect($e->validator->errors()->all())->implode(' ');

            return Response::error("Validation failed: {$messages}");
        } catch (\Throwable $e) {
            return Response::error("Failed to create draft product: {$e->getMessage()}");
        }

        return Response::json($this->formatOutput($listing));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'seller_email' => $schema->string()
                ->description('Email address of the seller creating the product. Required if not authenticated via MCP session.'),
            'seller_id' => $schema->integer()
                ->description('User ID of the seller creating the product (alternative to seller_email).'),
            'title' => $schema->string()
                ->description('Clear, search-friendly product title (maximum 160 characters).')
                ->max(160)
                ->required(),
            'product_type' => $schema->string()
                ->description('Product structure: "simple" for standalone items without variations; "variant" for items with selectable options like size/color.')
                ->enum(['simple', 'variant'])
                ->default('simple')
                ->required(),
            'short_description' => $schema->string()
                ->description('Concise summary highlighting key selling points (maximum 160 characters). Useful for snippet previews and SEO.')
                ->max(160),
            'description' => $schema->string()
                ->description('Comprehensive product description. Can include HTML formatting or markdown (maximum 10,000 characters).'),
            'specifications_text' => $schema->string()
                ->description('Technical specifications or product details formatted as text (e.g. "Material: 100% Cotton\nWeight: 200g\nDimensions: 10x20cm").'),
            'condition' => $schema->string()
                ->description('Physical condition of the item.')
                ->enum(['new', 'used', 'refurbished'])
                ->default('new'),
            'category_id' => $schema->integer()
                ->description('Database ID of the specific leaf category for this product.'),
            'brand_name' => $schema->string()
                ->description('Brand or manufacturer name as text. Use this when the brand is not yet in the catalog. Mutually exclusive with brand_id.')
                ->max(160),
            'brand_id' => $schema->integer()
                ->description('Database ID of an existing catalog brand. Mutually exclusive with brand_name.'),
            'sku' => $schema->string()
                ->description('Base Stock Keeping Unit for inventory tracking. Must be unique per seller (maximum 100 characters).')
                ->max(100),
            'barcode' => $schema->string()
                ->description('Universal product barcode, UPC, or EAN string (maximum 100 characters).')
                ->max(100),
            'gtin' => $schema->string()
                ->description('Global Trade Item Number (8, 12, 13, or 14 numeric digits). Used for simple products.'),
            'mpn' => $schema->string()
                ->description('Manufacturer Part Number assigned by the maker (maximum 100 characters).')
                ->max(100),
            'model' => $schema->string()
                ->description('Specific model name or number (maximum 160 characters).')
                ->max(160),
            'selling_price' => $schema->number()
                ->description('Current active selling price. Required for simple products; serves as default base price for variants.'),
            'compare_price' => $schema->number()
                ->description('Original, list, or MSRP strike-through price. Must be greater than selling_price when specified.'),
            'stock_quantity' => $schema->integer()
                ->description('Initial inventory count on hand (0 to 100,000). For variant products, serves as default stock per variant if variants array is not supplied.')
                ->default(0),
            'low_stock_threshold' => $schema->integer()
                ->description('Inventory level threshold that triggers low-stock warnings (default 0).')
                ->default(0),
            'allow_backorders' => $schema->boolean()
                ->description('Whether customers can order this item when stock reaches zero.')
                ->default(false),
            'location' => $schema->string()
                ->description('Warehouse, city, or shelf location for fulfillment (maximum 120 characters).')
                ->max(120),
            'warranty' => $schema->string()
                ->description('Warranty period and coverage details (maximum 500 characters).')
                ->max(500),
            'meta_title' => $schema->string()
                ->description('SEO title tag for search engines (up to 60 characters). If omitted, automatically derived from the product title.')
                ->max(60),
            'meta_description' => $schema->string()
                ->description('SEO meta description for SERP snippets (up to 160 characters). If omitted, automatically generated from short_description or description.')
                ->max(160),
            'variant_options' => $schema->array()
                ->description('Option groups for variant products (maximum 3 options, e.g. Color and Size).')
                ->items(
                    $schema->object([
                        'name' => $schema->string()->description('Option dimension name, e.g. "Color", "Size", "Material"')->required(),
                        'values' => $schema->array()->description('List of possible values, e.g. ["Red", "Blue"] or ["Small", "Medium"]')->items($schema->string())->required(),
                    ])
                ),
            'variants' => $schema->array()
                ->description('Explicit variant combination matrix. Optional: if omitted for a variant product, all combinations will be generated automatically from variant_options using base price and stock.')
                ->items(
                    $schema->object([
                        'selections' => $schema->array()->description('Selected value for each option dimension, in matching order, e.g. ["Red", "Medium"]')->items($schema->string())->required(),
                        'sku' => $schema->string()->description('Specific SKU for this variant (optional, auto-generated if omitted)'),
                        'gtin' => $schema->string()->description('GTIN barcode for this variant (optional)'),
                        'mpn' => $schema->string()->description('Manufacturer Part Number for this variant (optional)'),
                        'selling_price' => $schema->number()->description('Selling price for this variant'),
                        'market_price' => $schema->number()->description('Original / compare price for this variant (must be > selling_price)'),
                        'stock_quantity' => $schema->integer()->description('Inventory count for this variant'),
                        'is_active' => $schema->boolean()->description('Whether this variant is active and available for purchase')->default(true),
                    ])
                ),
        ];
    }

    private function resolveSeller(Request $request): ?User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        if ($email = $request->get('seller_email')) {
            return User::where('email', (string) $email)->first();
        }

        if ($id = $request->get('seller_id')) {
            return User::find((int) $id);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAttributes(Request $request): array
    {
        $title = Str::squish((string) $request->get('title', ''));
        $shortDesc = $request->get('short_description');
        $desc = $request->get('description');
        $productType = (string) $request->get('product_type', 'simple');

        // Ensure SEO metadata is always populated
        $metaTitle = filled($request->get('meta_title'))
            ? Str::limit(Str::squish((string) $request->get('meta_title')), 60, '')
            : Str::limit($title, 60, '');

        $rawDescription = strip_tags((string) ($shortDesc ?? $desc ?? ''));
        $metaDescription = filled($request->get('meta_description'))
            ? Str::limit(Str::squish((string) $request->get('meta_description')), 160, '')
            : Str::limit(Str::squish($rawDescription), 160, '');

        $attributes = [
            ...$request->all([
                'category_id',
                'brand_id',
                'brand_name',
                'sku',
                'barcode',
                'gtin',
                'mpn',
                'model',
                'condition',
                'location',
                'specifications_text',
                'warranty',
                'low_stock_threshold',
                'allow_backorders',
            ]),
            'title' => $title,
            'short_description' => filled($shortDesc) ? Str::squish((string) $shortDesc) : null,
            'description' => filled($desc) ? trim((string) $desc) : null,
            'product_type' => $productType,
            'selling_price' => $request->get('selling_price'),
            'compare_price' => $request->get('compare_price'),
            'stock_quantity' => (int) $request->get('stock_quantity', 0),
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'submit_for_review' => false,
            'is_active' => true,
            'is_featured' => false,
            'is_best_seller' => false,
            'is_new_arrival' => false,
        ];

        if ($productType === 'variant') {
            $variantOptions = $request->get('variant_options', []);
            $variantRows = $request->get('variants', []);

            if (is_array($variantOptions) && ! empty($variantOptions)) {
                $attributes['variant_options'] = $variantOptions;

                // Auto-generate variant matrix if LLM did not provide explicit rows
                if (empty($variantRows)) {
                    $normalized = $this->variantService->normalizedOptions($variantOptions);
                    $combinations = $this->variantService->combinations($normalized);

                    $attributes['variants'] = collect($combinations)->map(function (array $selections) use ($attributes): array {
                        return [
                            'selections' => array_values($selections),
                            'sku' => null, // ListingVariantService suggests SKU automatically
                            'selling_price' => $attributes['selling_price'] ?? null,
                            'market_price' => $attributes['compare_price'] ?? null,
                            'stock_quantity' => $attributes['stock_quantity'],
                            'is_active' => true,
                        ];
                    })->all();
                } else {
                    $attributes['variants'] = $variantRows;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOutput(Listing $listing): array
    {
        $listing->loadMissing(['category', 'brand', 'variants', 'variantOptions']);

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'status' => $listing->status,
            'product_type' => $listing->product_type,
            'sku' => $listing->sku,
            'price' => $listing->price,
            'sale_price' => $listing->sale_price,
            'stock_quantity' => $listing->stock_quantity,
            'condition' => $listing->condition,
            'brand' => $listing->brand->name ?? $listing->brand_name,
            'category' => $listing->category?->name,
            'meta_title' => $listing->meta_title,
            'meta_description' => $listing->meta_description,
            'has_specifications' => filled($listing->specifications),
            'variants_count' => $listing->variants->count(),
            'message' => "Draft {$listing->product_type} product created successfully with ID {$listing->id}. It remains in draft status until images and review submission are completed.",
        ];
    }
}
