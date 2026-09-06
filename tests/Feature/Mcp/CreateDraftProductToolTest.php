<?php

use App\Mcp\Servers\MarketplaceServer;
use App\Mcp\Tools\CreateDraftProductTool;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\ListingService;
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
            'variant_options',
            'variants',
        ]);
});
