<?php

use App\Mcp\Servers\MarketplaceServer;
use App\Mcp\Tools\CreateDraftProductTool;
use App\Mcp\Tools\SearchProductCategoriesTool;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\ListingService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('creates a simple draft product using seller_email and auto-generates seo tags', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create(['commission_percentage' => 10]);

    $response = MarketplaceServer::tool(CreateDraftProductTool::class, [
        'seller_email' => $seller->user->email,
        'title' => 'Vintage Mechanical Keyboard',
        'short_description' => 'A timeless mechanical keyboard with clicky switches.',
        'description' => '<p>Detailed description of the keyboard.</p>',
        'sku' => 'KEYB-VINT-001',
        'condition' => 'used',
        'product_type' => 'simple',
        'category_id' => $category->id,
        'brand_name' => 'IBM',
        'selling_price' => 15000,
        'compare_price' => 18000,
        'stock_quantity' => 3,
        'specifications_text' => "Switches: Cherry MX Blue\nLayout: ANSI",
    ]);

    $response->assertOk()
        ->assertHasNoErrors();

    $listing = Listing::query()->where('sku', 'KEYB-VINT-001')->sole();

    expect($listing->status)->toBe('draft')
        ->and($listing->title)->toBe('Vintage Mechanical Keyboard')
        ->and($listing->meta_title)->toBe('Vintage Mechanical Keyboard')
        ->and($listing->meta_description)->toBe('A timeless mechanical keyboard with clicky switches.')
        ->and($listing->product_type)->toBe('simple')
        ->and((float) $listing->price)->toBe(18000.0)
        ->and((float) $listing->sale_price)->toBe(15000.0)
        ->and($listing->stock_quantity)->toBe(3)
        ->and($listing->specifications['Details'])->toContain('Switches: Cherry MX Blue');
});

test('creates a draft product with explicit seo tags when user is authenticated', function () {
    $seller = SellerProfile::factory()->create();

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Wireless Noise Cancelling Headphones',
            'product_type' => 'simple',
            'sku' => 'AUDIO-NC-001',
            'selling_price' => 25000,
            'meta_title' => 'Buy Noise Cancelling Headphones | Best Audio Gear',
            'meta_description' => 'Shop top-rated active noise cancelling headphones with premium sound quality and 30-hour battery life.',
        ]);

    $response->assertOk()
        ->assertHasNoErrors();

    $listing = Listing::query()->where('sku', 'AUDIO-NC-001')->sole();

    expect($listing->status)->toBe('draft')
        ->and($listing->meta_title)->toBe('Buy Noise Cancelling Headphones | Best Audio Gear')
        ->and($listing->meta_description)->toBe('Shop top-rated active noise cancelling headphones with premium sound quality and 30-hour battery life.');
});

test('resolves seller by seller_id', function () {
    $seller = SellerProfile::factory()->create();

    $response = MarketplaceServer::tool(CreateDraftProductTool::class, [
        'seller_id' => $seller->user->id,
        'title' => 'Ergonomic Desk Chair',
        'product_type' => 'simple',
        'sku' => 'CHAIR-ERGO-001',
        'selling_price' => 45000,
    ]);

    $response->assertOk()
        ->assertHasNoErrors();

    expect(Listing::query()->where('sku', 'CHAIR-ERGO-001')->exists())->toBeTrue();
});

test('creates a variant draft product with auto-generated variant rows from variant_options', function () {
    $seller = SellerProfile::factory()->create();

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Organic Cotton Graphic T-Shirt',
            'product_type' => 'variant',
            'sku' => 'TSHIRT-ORG',
            'selling_price' => 3500,
            'compare_price' => 4500,
            'stock_quantity' => 15,
            'variant_options' => [
                [
                    'name' => 'Color',
                    'values' => ['Black', 'White'],
                ],
                [
                    'name' => 'Size',
                    'values' => ['M', 'L'],
                ],
            ],
        ]);

    $response->assertOk()
        ->assertHasNoErrors();

    $listing = Listing::query()->where('sku', 'TSHIRT-ORG')->sole();

    expect($listing->status)->toBe('draft')
        ->and($listing->product_type)->toBe('variant')
        ->and($listing->variantOptions)->toHaveCount(2)
        ->and($listing->variants)->toHaveCount(4)
        ->and($listing->stock_quantity)->toBe(60); // 4 variants * 15 each
});

test('creates a variant draft product with explicit variant matrix', function () {
    $seller = SellerProfile::factory()->create();

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Custom Leather Belt',
            'product_type' => 'variant',
            'sku' => 'BELT-LTHR',
            'variant_options' => [
                [
                    'name' => 'Size',
                    'values' => ['32', '34'],
                ],
            ],
            'variants' => [
                [
                    'selections' => ['32'],
                    'sku' => 'BELT-LTHR-32',
                    'selling_price' => 5000,
                    'market_price' => 6000,
                    'stock_quantity' => 8,
                    'is_active' => true,
                ],
                [
                    'selections' => ['34'],
                    'sku' => 'BELT-LTHR-34',
                    'selling_price' => 5200,
                    'market_price' => 6200,
                    'stock_quantity' => 12,
                    'is_active' => true,
                ],
            ],
        ]);

    $response->assertOk()
        ->assertHasNoErrors();

    $listing = Listing::query()->where('sku', 'BELT-LTHR')->sole();

    expect($listing->variants)->toHaveCount(2)
        ->and($listing->stock_quantity)->toBe(20);
});

test('returns error when seller cannot be identified', function () {
    $response = MarketplaceServer::tool(CreateDraftProductTool::class, [
        'title' => 'Some Product',
        'product_type' => 'simple',
    ]);

    $response->assertHasErrors(['Unable to identify seller. Please provide a valid "seller_email" or "seller_id", or authenticate the session.']);
});

test('returns error when seller has no seller profile', function () {
    $user = User::factory()->create();

    $response = MarketplaceServer::tool(CreateDraftProductTool::class, [
        'seller_email' => $user->email,
        'title' => 'Some Product',
        'product_type' => 'simple',
    ]);

    $response->assertHasErrors(["User [{$user->email}] does not have an associated seller profile."]);
});

test('returns error when validation fails', function () {
    $seller = SellerProfile::factory()->create();

    $mock = mock(ListingService::class);
    $mock->shouldReceive('createDraft')
        ->once()
        ->andThrow(ValidationException::withMessages(['title' => 'The title field is required.']));
    app()->instance(ListingService::class, $mock);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Invalid Product',
            'product_type' => 'simple',
        ]);

    $response->assertHasErrors(['Validation failed: The title field is required.']);
});

test('returns error when an unexpected exception occurs', function () {
    $seller = SellerProfile::factory()->create();

    $mock = mock(ListingService::class);
    $mock->shouldReceive('createDraft')
        ->once()
        ->andThrow(new RuntimeException('Database connection failed'));
    app()->instance(ListingService::class, $mock);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'A Product',
            'product_type' => 'simple',
        ]);

    $response->assertHasErrors(['Failed to create draft product: Database connection failed']);
});

test('exports valid json schema for mcp tool discovery', function () {
    $tool = app(CreateDraftProductTool::class);
    $array = $tool->toArray();

    expect($tool->name())->toBe('create-draft-product')
        ->and($tool->title())->toBe('Create Draft Product')
        ->and($array)->toHaveKey('inputSchema')
        ->and($array['inputSchema']['properties'])->toHaveKeys([
            'seller_email',
            'seller_id',
            'title',
            'product_type',
            'meta_title',
            'meta_description',
            'image_urls',
            'image_files',
            'image_generation_prompt',
            'variant_options',
            'variants',
        ])
        ->and($array['annotations'])->toMatchArray([
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'openWorldHint' => true,
        ]);
});

test('creates a draft and generates its product image in one tool call', function () {
    Storage::fake('r2');
    Queue::fake();
    Http::preventStrayRequests();

    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.product_images.model' => 'gpt-image-2',
    ]);

    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create(['is_selectable' => true]);
    $generatedImage = UploadedFile::fake()->image('generated.jpg', 1024, 1024);

    Http::fake([
        'https://api.openai.com/v1/images/generations' => Http::response([
            'data' => [[
                'b64_json' => base64_encode((string) file_get_contents($generatedImage->getPathname())),
            ]],
        ]),
    ]);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Printed Coffee Mug',
            'product_type' => 'variant',
            'description' => 'Printed coffee mugs in three colours.',
            'condition' => 'new',
            'category_id' => $category->id,
            'brand_name' => 'ProDeals',
            'sku' => 'MUG-PRINTED',
            'selling_price' => 250,
            'variant_options' => [[
                'name' => 'Color',
                'values' => ['Black', 'White', 'Red'],
            ]],
            'image_generation_prompt' => 'Studio product photo of black, white, and red printed coffee mugs on a clean background.',
        ]);

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee(['"images_count":1', '"ready_for_review":true']);

    $listing = Listing::query()->where('sku', 'MUG-PRINTED')->sole();
    $media = $listing->media()->sole();

    expect($listing->variants)->toHaveCount(3)
        ->and($media->disk)->toBe('r2')
        ->and($media->crop_width)->toBe(1024)
        ->and($media->crop_height)->toBe(1024);

    Storage::disk('r2')->assertExists([$media->source_path, $media->path]);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request['model'] === 'gpt-image-2'
            && $request['quality'] === 'low'
            && $request['size'] === '1024x1024'
            && $request['output_format'] === 'jpeg'
            && $request['output_compression'] === 80
            && $request->hasHeader('Authorization', 'Bearer test-openai-key');
    });
});

test('creates a draft with an existing base64 image in one tool call', function () {
    Storage::fake('r2');
    Queue::fake();

    $seller = SellerProfile::factory()->create();
    $sourceImage = UploadedFile::fake()->image('generated.png', 1200, 800);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Generated Image Product',
            'product_type' => 'simple',
            'selling_price' => 250,
            'image_files' => [[
                'filename' => 'generated.png',
                'content_base64' => base64_encode((string) file_get_contents($sourceImage->getPathname())),
            ]],
        ]);

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee(['"images_count":1']);

    $media = Listing::query()->where('title', 'Generated Image Product')->sole()->media()->sole();

    expect($media->crop_x)->toBe(200)
        ->and($media->crop_y)->toBe(0)
        ->and($media->crop_width)->toBe(800)
        ->and($media->crop_height)->toBe(800);
});

test('creates a review-ready simple draft with remote gallery images stored on r2', function () {
    Storage::fake('r2');
    Queue::fake();

    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create(['is_selectable' => true]);
    $sourceImage = UploadedFile::fake()->image('product.png', 1200, 800);
    $imageBody = file_get_contents($sourceImage->getPathname());

    Http::fake([
        'https://93.184.216.34/product.png' => Http::response($imageBody, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen((string) $imageBody),
        ]),
    ]);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Complete Product',
            'product_type' => 'simple',
            'description' => 'A complete description for review.',
            'condition' => 'new',
            'category_id' => $category->id,
            'brand_name' => 'ProDeals',
            'sku' => 'COMPLETE-001',
            'selling_price' => 12000,
            'stock_quantity' => 10,
            'image_urls' => ['https://93.184.216.34/product.png'],
        ]);

    $response->assertOk()
        ->assertSee(['"images_count":1', '"ready_for_review":true', 'final review']);

    $listing = Listing::query()->where('sku', 'COMPLETE-001')->sole();
    $media = $listing->media()->sole();

    expect($listing->status)->toBe('draft')
        ->and($media->disk)->toBe('r2')
        ->and($media->crop_x)->toBe(200)
        ->and($media->crop_y)->toBe(0)
        ->and($media->crop_width)->toBe(800)
        ->and($media->crop_height)->toBe(800);

    Storage::disk('r2')->assertExists([$media->source_path, $media->path]);
});

test('stores a remote image for an exact product variant', function () {
    Storage::fake('r2');
    Queue::fake();

    $seller = SellerProfile::factory()->create();
    $sourceImage = UploadedFile::fake()->image('shirt.jpg', 900, 900);
    $imageBody = file_get_contents($sourceImage->getPathname());

    Http::fake([
        'https://93.184.216.34/gallery.jpg' => Http::response($imageBody, 200, ['Content-Type' => 'image/jpeg']),
        'https://93.184.216.34/red.jpg' => Http::response($imageBody, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Image Variant Shirt',
            'product_type' => 'variant',
            'sku' => 'SHIRT-IMAGE',
            'selling_price' => 4500,
            'image_urls' => ['https://93.184.216.34/gallery.jpg'],
            'variant_options' => [[
                'name' => 'Color',
                'values' => ['Red'],
            ]],
            'variants' => [[
                'selections' => ['Red'],
                'sku' => 'SHIRT-IMAGE-RED',
                'selling_price' => 4500,
                'stock_quantity' => 4,
                'image_url' => 'https://93.184.216.34/red.jpg',
            ]],
        ]);

    $response->assertOk()
        ->assertSee(['"variants_count":1', '"image_url":']);

    $variant = Listing::query()->where('sku', 'SHIRT-IMAGE')->sole()->variants()->sole();
    $variantImage = $variant->image()->sole();

    expect($variantImage->disk)->toBe('r2')
        ->and($variantImage->type)->toBe('variant_image');

    Storage::disk('r2')->assertExists([$variantImage->source_path, $variantImage->path]);
    Http::assertSentCount(2);
});

test('rejects remote images hosted on private addresses before making a request', function () {
    Http::preventStrayRequests();
    $seller = SellerProfile::factory()->create();

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Unsafe Image Product',
            'product_type' => 'simple',
            'image_urls' => ['https://127.0.0.1/internal.png'],
        ]);

    $response->assertHasErrors(['Image URLs must resolve only to public internet addresses.']);

    expect(Listing::query()->where('title', 'Unsafe Image Product')->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('rejects downloaded files that are not supported images', function () {
    Http::fake([
        'https://93.184.216.34/not-image.txt' => Http::response('not an image', 200, ['Content-Type' => 'text/plain']),
    ]);
    $seller = SellerProfile::factory()->create();

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(CreateDraftProductTool::class, [
            'title' => 'Invalid Image Product',
            'product_type' => 'simple',
            'image_urls' => ['https://93.184.216.34/not-image.txt'],
        ]);

    $response->assertHasErrors(['Images must be JPEG, PNG, or WebP files.']);

    expect(Listing::query()->where('title', 'Invalid Image Product')->exists())->toBeFalse();
});

test('suggests selectable category ids for product creation', function () {
    config()->set('services.openai.api_key');
    Category::factory()->create([
        'name' => 'Mechanical Keyboards',
        'slug' => 'mechanical-keyboards',
        'is_selectable' => true,
    ]);

    MarketplaceServer::tool(SearchProductCategoriesTool::class, [
        'title' => 'Mechanical keyboard',
        'limit' => 3,
    ])->assertOk()
        ->assertSee(['"category_id":', 'Mechanical Keyboards']);

    $tool = app(SearchProductCategoriesTool::class);

    expect($tool->toArray()['annotations'])->toMatchArray([
        'readOnlyHint' => true,
        'openWorldHint' => true,
    ]);
});
