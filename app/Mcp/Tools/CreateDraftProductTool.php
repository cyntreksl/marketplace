<?php

namespace App\Mcp\Tools;

use App\Models\Listing;
use App\Models\User;
use App\Rules\ValidGtin;
use App\Services\ListingService;
use App\Services\ListingVariantService;
use App\Services\ProductImageGenerationService;
use App\Services\RemoteListingImageService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create-draft-product')]
#[Title('Create Draft Product')]
#[Description('Creates a complete draft product listing with details, variants, and up to five images. For a newly requested AI product photo, send image_generation_prompt so ProDeals generates and attaches it in this same call. Existing public HTTPS images or base64 image files are also accepted. Returns missing review requirements and always remains a draft.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld]
class CreateDraftProductTool extends Tool
{
    public function __construct(
        private readonly ListingService $listings,
        private readonly ListingVariantService $variantService,
        private readonly RemoteListingImageService $remoteImages,
        private readonly ProductImageGenerationService $imageGeneration,
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

        /** @var array<int, UploadedFile> $temporaryUploads */
        $temporaryUploads = [];

        try {
            $this->validateRequest($request, $seller);
            $attributes = $this->prepareAttributes($request);
            $this->attachImages($attributes, $request, $temporaryUploads);
            $listing = $this->listings->createDraft($seller, $attributes);
        } catch (ValidationException $e) {
            $messages = collect($e->validator->errors()->all())->implode(' ');

            return Response::error("Validation failed: {$messages}");
        } catch (\Throwable $e) {
            return Response::error("Failed to create draft product: {$e->getMessage()}");
        } finally {
            foreach ($temporaryUploads as $upload) {
                $this->remoteImages->removeTemporaryUpload($upload);
            }
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
                ->description('Comprehensive product description. Can include HTML formatting or markdown (maximum 10,000 characters).')
                ->max(10000),
            'specifications_text' => $schema->string()
                ->description('Technical specifications or product details formatted as text (e.g. "Material: 100% Cotton\nWeight: 200g\nDimensions: 10x20cm").')
                ->max(10000),
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
            'image_urls' => $schema->array()
                ->description('One to five direct public HTTPS image URLs. JPEG, PNG, and WebP are accepted up to 5 MB each. The first image becomes the cover and images are center-cropped to square.')
                ->min(1)
                ->max(5)
                ->items($schema->string()->format('uri')->max(2048)),
            'image_files' => $schema->array()
                ->description('JPEG, PNG, or WebP files supplied as base64 content. Use when an existing generated image has no public URL.')
                ->min(1)
                ->max(5)
                ->items($schema->object([
                    'filename' => $schema->string()->description('Original filename, including extension.')->max(255)->required(),
                    'content_base64' => $schema->string()->description('Raw base64 file content or a data:image/...;base64 data URL. Maximum decoded size is 5 MB.')->max(7100000)->required(),
                ])),
            'image_generation_prompt' => $schema->string()
                ->description('A concise prompt for one new product photo. ProDeals generates a fast square JPEG and attaches it as the cover during this create call. Use this instead of calling a separate image tool.')
                ->max(2000),
            'is_active' => $schema->boolean()
                ->description('Whether the listing will be active after approval and publishing.')
                ->default(true),
            'is_featured' => $schema->boolean()
                ->description('Seller merchandising flag for a featured product.')
                ->default(false),
            'is_best_seller' => $schema->boolean()
                ->description('Seller merchandising flag for a best-seller product.')
                ->default(false),
            'is_new_arrival' => $schema->boolean()
                ->description('Seller merchandising flag for a new-arrival product.')
                ->default(false),
            'variant_options' => $schema->array()
                ->description('Option groups for variant products (maximum 3 options, e.g. Color and Size).')
                ->max(3)
                ->items(
                    $schema->object([
                        'name' => $schema->string()->description('Option dimension name, e.g. "Color", "Size", "Material"')->max(80)->required(),
                        'values' => $schema->array()->description('List of possible values, e.g. ["Red", "Blue"] or ["Small", "Medium"]')->min(1)->items($schema->string()->max(100))->required(),
                    ])
                ),
            'variants' => $schema->array()
                ->description('Explicit variant combination matrix. Optional: if omitted for a variant product, all combinations will be generated automatically from variant_options using base price and stock.')
                ->max(100)
                ->items(
                    $schema->object([
                        'selections' => $schema->array()->description('Selected value for each option dimension, in matching order, e.g. ["Red", "Medium"]')->max(3)->items($schema->string()->max(100))->required(),
                        'sku' => $schema->string()->description('Specific SKU for this variant (optional, auto-generated if omitted)')->max(100),
                        'gtin' => $schema->string()->description('GTIN barcode for this variant (optional)'),
                        'mpn' => $schema->string()->description('Manufacturer Part Number for this variant (optional)')->max(100),
                        'selling_price' => $schema->number()->description('Selling price for this variant'),
                        'market_price' => $schema->number()->description('Original / compare price for this variant (must be > selling_price)'),
                        'stock_quantity' => $schema->integer()->description('Inventory count for this variant'),
                        'is_active' => $schema->boolean()->description('Whether this variant is active and available for purchase')->default(true),
                        'image_url' => $schema->string()->description('Direct public HTTPS JPEG, PNG, or WebP image URL for this exact variant (optional, maximum 5 MB).')->format('uri')->max(2048),
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
                'is_active',
                'is_featured',
                'is_best_seller',
                'is_new_arrival',
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
            'is_active' => filter_var($request->get('is_active', true), FILTER_VALIDATE_BOOL),
            'is_featured' => filter_var($request->get('is_featured', false), FILTER_VALIDATE_BOOL),
            'is_best_seller' => filter_var($request->get('is_best_seller', false), FILTER_VALIDATE_BOOL),
            'is_new_arrival' => filter_var($request->get('is_new_arrival', false), FILTER_VALIDATE_BOOL),
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
        $listing->loadMissing([
            'category',
            'brand',
            'media',
            'variants.image',
            'variants.optionValues.option',
            'variantOptions',
        ]);
        $missingRequirements = $this->missingReviewRequirements($listing);

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
            'images_count' => $listing->media->count(),
            'images' => $listing->media->values()->map(fn ($media, int $index): array => [
                'id' => $media->id,
                'url' => $media->url,
                'is_cover' => $index === 0,
            ])->all(),
            'variants_count' => $listing->variants->count(),
            'variants' => $listing->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'selections' => $variant->optionValues->sortBy('option.position')->mapWithKeys(
                    fn ($value): array => [$value->option->name => $value->value],
                )->all(),
                'sku' => $variant->sku,
                'gtin' => $variant->gtin,
                'mpn' => $variant->mpn,
                'selling_price' => $variant->selling_price,
                'market_price' => $variant->market_price,
                'stock_quantity' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
                'image_url' => $variant->image?->url,
            ])->all(),
            'ready_for_review' => $missingRequirements === [],
            'missing_review_requirements' => $missingRequirements,
            'message' => $missingRequirements === []
                ? "Draft {$listing->product_type} product created successfully with ID {$listing->id}. It is ready for the seller's final review, but has not been submitted or published."
                : "Draft {$listing->product_type} product created successfully with ID {$listing->id}. Complete the reported requirements before final review and submission.",
        ];
    }

    private function validateRequest(Request $request, User $seller): void
    {
        $sellerProfileId = $seller->sellerProfile()->value('id');

        $request->validate([
            'seller_email' => ['nullable', 'email:rfc', 'max:255'],
            'seller_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'product_type' => ['required', Rule::in(['simple', 'variant'])],
            'short_description' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'specifications_text' => ['nullable', 'string', 'max:10000'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id', 'prohibits:brand_name'],
            'brand_name' => ['nullable', 'string', 'max:160', 'prohibits:brand_id'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                'regex:/\A[\x21-\x7E]+\z/',
                Rule::unique('listings', 'sku')->where('seller_profile_id', $sellerProfileId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('listings', 'barcode')->where('seller_profile_id', $sellerProfileId),
            ],
            'gtin' => [Rule::excludeIf($request->get('product_type') === 'variant'), 'nullable', new ValidGtin],
            'mpn' => [Rule::excludeIf($request->get('product_type') === 'variant'), 'nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:160'],
            'selling_price' => ['nullable', 'numeric', 'min:1'],
            'compare_price' => ['nullable', 'numeric', 'gt:selling_price'],
            'stock_quantity' => ['nullable', 'integer', 'between:0,100000'],
            'low_stock_threshold' => ['nullable', 'integer', 'between:0,100000'],
            'allow_backorders' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:120'],
            'warranty' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_best_seller' => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],
            'image_urls' => ['nullable', 'array', 'between:1,5'],
            'image_urls.*' => ['required', 'string', 'url:https', 'max:2048', 'distinct'],
            'image_files' => ['nullable', 'array', 'between:1,5'],
            'image_files.*.filename' => ['required', 'string', 'max:255'],
            'image_files.*.content_base64' => ['required', 'string', 'max:7100000'],
            'image_generation_prompt' => ['nullable', 'string', 'max:2000'],
            'variant_options' => ['nullable', 'array', 'max:3'],
            'variant_options.*.name' => ['required', 'string', 'max:80'],
            'variant_options.*.values' => ['required', 'array', 'min:1'],
            'variant_options.*.values.*' => ['required', 'string', 'max:100', 'distinct'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.selections' => ['required', 'array', 'max:3'],
            'variants.*.selections.*' => ['required', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:100', 'regex:/\A[\x21-\x7E]+\z/'],
            'variants.*.gtin' => ['nullable', new ValidGtin],
            'variants.*.mpn' => ['nullable', 'string', 'max:100'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:1'],
            'variants.*.market_price' => ['nullable', 'numeric', 'min:1'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'between:0,100000'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.image_url' => ['nullable', 'string', 'url:https', 'max:2048'],
        ]);

        $galleryImageCount = count((array) $request->get('image_urls', []))
            + count((array) $request->get('image_files', []))
            + (filled($request->get('image_generation_prompt')) ? 1 : 0);

        if ($galleryImageCount > 5) {
            throw ValidationException::withMessages([
                'images' => 'Image URLs, image files, and the generated image may not total more than five gallery images.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $temporaryUploads
     */
    private function attachImages(array &$attributes, Request $request, array &$temporaryUploads): void
    {
        $imageUrls = $request->get('image_urls', []);

        foreach (is_array($imageUrls) ? array_values($imageUrls) : [] as $index => $imageUrl) {
            $download = $this->remoteImages->download((string) $imageUrl, "image_urls.{$index}");
            $attributes['images'][] = $download['upload'];
            $attributes['image_crops'][] = $download['crop'];
            $temporaryUploads[] = $download['upload'];
        }

        foreach ((array) $request->get('image_files', []) as $index => $file) {
            $prepared = $this->remoteImages->decodeBase64(
                (string) ($file['content_base64'] ?? ''),
                (string) ($file['filename'] ?? 'product-image'),
                "image_files.{$index}.content_base64",
            );
            $attributes['images'][] = $prepared['upload'];
            $attributes['image_crops'][] = $prepared['crop'];
            $temporaryUploads[] = $prepared['upload'];
        }

        if (filled($request->get('image_generation_prompt'))) {
            $generated = $this->imageGeneration->generate(
                Str::squish((string) $request->get('image_generation_prompt')),
                'image_generation_prompt',
            );
            $attributes['images'][] = $generated['upload'];
            $attributes['image_crops'][] = $generated['crop'];
            $temporaryUploads[] = $generated['upload'];
        }

        $variants = $attributes['variants'] ?? [];

        if (! is_array($variants)) {
            return;
        }

        foreach ($variants as $index => &$variant) {
            if (! is_array($variant) || ! filled($variant['image_url'] ?? null)) {
                continue;
            }

            $download = $this->remoteImages->download((string) $variant['image_url'], "variants.{$index}.image_url");
            $variant['image'] = $download['upload'];
            $variant['image_crop'] = $download['crop'];
            unset($variant['image_url']);
            $temporaryUploads[] = $download['upload'];
        }
        unset($variant);

        $attributes['variants'] = $variants;
    }

    /** @return array<int, string> */
    private function missingReviewRequirements(Listing $listing): array
    {
        $requirements = [
            'category' => $listing->category_id !== null,
            'brand' => filled($listing->brand_id) || filled($listing->brand_name),
            'sku' => filled($listing->sku),
            'description' => filled($listing->description),
            'condition' => filled($listing->condition),
            'price' => $listing->price !== null && (float) $listing->price >= 1,
            'product_image' => $listing->media->isNotEmpty(),
        ];

        if ($listing->product_type === 'variant') {
            $requirements['variants'] = $listing->variants->isNotEmpty();
            $requirements['variant_skus'] = $listing->variants->isNotEmpty()
                && $listing->variants->every(fn ($variant): bool => filled($variant->sku));
        }

        return collect($requirements)
            ->reject(fn (bool $isComplete): bool => $isComplete)
            ->keys()
            ->values()
            ->all();
    }
}
