<?php

use App\Mcp\Servers\MarketplaceServer;
use App\Mcp\Tools\UpdateDraftProductTool;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Models\SellerProfile;
use App\Models\SeoRedirect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('adds a generated image file to an existing draft and stores it on r2', function (array $changes) {
    Storage::fake('r2');
    Queue::fake();

    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'approved_at' => null,
    ]);
    $sourceImage = UploadedFile::fake()->image('generated.png', 1200, 800);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(UpdateDraftProductTool::class, [
            ...$changes,
            'listing_id' => $listing->id,
            'image_files' => [[
                'filename' => 'generated.png',
                'content_base64' => base64_encode((string) file_get_contents($sourceImage->getPathname())),
            ]],
        ]);

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee(['"images_count":1', '"ready_for_review":true']);

    $media = $listing->media()->sole();

    expect($listing->fresh()->status)->toBe('draft')
        ->and($listing->fresh()->meta_title)->not->toBeEmpty()
        ->and($listing->fresh()->meta_description)->not->toBeEmpty()
        ->and($media->disk)->toBe('r2')
        ->and($media->crop_x)->toBe(200)
        ->and($media->crop_y)->toBe(0)
        ->and($media->crop_width)->toBe(800)
        ->and($media->crop_height)->toBe(800);

    Storage::disk('r2')->assertExists([$media->source_path, $media->path]);

    expect($listing->fresh()->only(array_keys($changes)))->toBe($changes);
})->with([
    'image only' => [[]],
    'image and text' => [['title' => 'Updated product with image', 'warranty' => 'One year']],
]);

test('does not allow a seller to update another seller draft', function () {
    Storage::fake('r2');
    Queue::fake();

    $owner = SellerProfile::factory()->create();
    $otherSeller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $owner->id,
        'status' => 'draft',
    ]);
    $sourceImage = UploadedFile::fake()->image('generated.png');

    $response = MarketplaceServer::actingAs($otherSeller->user)
        ->tool(UpdateDraftProductTool::class, [
            'listing_id' => $listing->id,
            'image_files' => [[
                'filename' => 'generated.png',
                'content_base64' => base64_encode((string) file_get_contents($sourceImage->getPathname())),
            ]],
        ]);

    $response->assertHasErrors();

    expect($listing->media()->exists())->toBeFalse();
});

test('rejects invalid base64 image content', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
    ]);

    $response = MarketplaceServer::actingAs($seller->user)
        ->tool(UpdateDraftProductTool::class, [
            'listing_id' => $listing->id,
            'image_files' => [[
                'filename' => 'generated.png',
                'content_base64' => 'not-base64!',
            ]],
        ]);

    $response->assertHasErrors(['Validation failed: The image content must be valid base64.']);
});

test('exports partial content updates and image uploads for mcp discovery', function () {
    $tool = app(UpdateDraftProductTool::class);
    $array = $tool->toArray();

    expect($tool->name())->toBe('update-draft-product')
        ->and($tool->title())->toBe('Update Draft Product')
        ->and($array['inputSchema']['properties'])->toHaveKeys([
            'seller_email',
            'seller_id',
            'listing_id',
            'image_urls',
            'image_files',
            'title',
            'short_description',
            'description',
            'specifications_text',
            'warranty',
            'meta_title',
            'meta_description',
        ])
        ->and($array['inputSchema']['required'])->toBe(['listing_id'])
        ->and($array['annotations'])->toMatchArray([
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'openWorldHint' => true,
        ]);
});

test('updates draft content without images or a duplicate listing', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'approved_at' => null,
        'specifications' => ['Old' => 'Specification'],
    ]);
    $originalTitle = $listing->title;
    $originalSlug = $listing->slug;

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'title' => 'Updated Laptop',
        'short_description' => 'Portable workstation',
        'description' => 'Updated full product description.',
        'specifications_text' => "Memory: 32 GB\nStorage: 1 TB SSD",
        'warranty' => 'Two years parts and labour',
    ])->assertHasNoErrors()->assertSee('updated successfully');

    $listing->refresh();

    expect(Listing::query()->count())->toBe(1)
        ->and($listing->title)->toBe('Updated Laptop')
        ->and($listing->slug)->toBe('updated-laptop')
        ->and($listing->short_description)->toBe('Portable workstation')
        ->and($listing->description)->toBe('Updated full product description.')
        ->and($listing->specifications)->toBe(['Details' => "Memory: 32 GB\nStorage: 1 TB SSD"])
        ->and($listing->warranty)->toBe('Two years parts and labour')
        ->and($listing->status)->toBe('draft')
        ->and($listing->approved_at)->toBeNull()
        ->and($listing->media)->toBeEmpty()
        ->and(SeoRedirect::query()->where('source_path', '/listings/'.$originalSlug)->value('destination_path'))
        ->toBe('/listings/updated-laptop');

    $audit = AuditLog::query()->where('action', 'listing.draft_updated')->sole();
    expect($audit->actor_id)->toBe($seller->user_id)
        ->and($audit->before['title'])->toBe($originalTitle)
        ->and($audit->after['title'])->toBe('Updated Laptop');
});

test('preserves omitted product fields and relations during a single field update', function (string $field, string $value) {
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'product_type' => 'variant',
        'specifications' => ['Memory' => '16 GB', 'Storage' => '512 GB'],
        'warranty' => 'Original warranty',
        'meta_title' => 'Original SEO title',
        'meta_description' => 'Original SEO description',
    ]);
    $media = ListingMedia::factory()->for($listing)->create(['disk' => 'r2']);
    $variant = ListingVariant::factory()->for($listing)->create();
    $before = $listing->fresh()->getAttributes();
    $variantBefore = $variant->fresh()->getAttributes();

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        $field => $value,
        'status' => 'approved',
        'submit_for_review' => true,
        'stock_quantity' => 999,
    ])->assertHasNoErrors();

    $listing->refresh();
    $changedFields = ['updated_at', $field === 'specifications_text' ? 'specifications' : $field];
    if ($field === 'title') {
        $changedFields[] = 'slug';
        $changedFields[] = 'meta_title';
    }
    if (in_array($field, ['title', 'short_description', 'description'], true)) {
        $changedFields[] = 'meta_description';
    }

    expect(Arr::except($listing->getAttributes(), $changedFields))->toBe(Arr::except($before, $changedFields))
        ->and($listing->media()->sole()->id)->toBe($media->id)
        ->and($listing->variants()->sole()->getAttributes())->toBe($variantBefore);

    if ($field === 'specifications_text') {
        expect($listing->specifications)->toBe(['Details' => $value]);
    } else {
        expect($listing->getAttribute($field))->toBe($value);
    }
})->with([
    ['title', 'Replacement title'],
    ['short_description', 'Replacement summary'],
    ['description', 'Replacement description'],
    ['specifications_text', 'Memory: 32 GB'],
    ['warranty', 'Replacement warranty'],
]);

test('clears optional content only when explicitly supplied', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'short_description' => 'Original summary',
        'specifications' => ['Details' => 'Original details'],
        'warranty' => 'Original warranty',
    ]);

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'short_description' => null,
        'description' => null,
        'specifications_text' => null,
        'warranty' => null,
    ])->assertHasNoErrors();

    expect($listing->fresh()->only(['short_description', 'description', 'specifications', 'warranty']))
        ->toBe(['short_description' => null, 'description' => null, 'specifications' => null, 'warranty' => null]);
});

test('rejects invalid or empty content updates without changing the draft', function (array $changes) {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => 'draft']);
    $before = $listing->fresh()->getAttributes();

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        ...$changes,
    ])->assertHasErrors();

    expect($listing->fresh()->getAttributes())->toBe($before)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'no changes' => [[]],
    'unsupported field only' => [['specifications' => 'Memory: 32 GB']],
    'null images only' => [['image_files' => null, 'image_urls' => null]],
    'empty title' => [['title' => '']],
    'null title' => [['title' => null]],
    'long title' => [['title' => str_repeat('a', 161)]],
    'long summary' => [['short_description' => str_repeat('a', 161)]],
    'long description' => [['description' => str_repeat('a', 10001)]],
    'long specifications' => [['specifications_text' => str_repeat('a', 10001)]],
    'long warranty' => [['warranty' => str_repeat('a', 501)]],
    'wrong type' => [['description' => ['invalid']]],
    'long SEO title' => [['meta_title' => str_repeat('a', 61)]],
    'long SEO description' => [['meta_description' => str_repeat('a', 161)]],
    'invalid SEO type' => [['meta_description' => ['invalid']]],
]);

test('denies text updates to other sellers even with an explicit seller id', function () {
    $owner = SellerProfile::factory()->create();
    $otherSeller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $owner->id, 'status' => 'draft']);
    $before = $listing->fresh()->getAttributes();

    MarketplaceServer::actingAs($otherSeller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'seller_id' => $owner->user_id,
        'title' => 'Unauthorized update',
    ])->assertHasErrors();

    expect($listing->fresh()->getAttributes())->toBe($before);
});

test('rejects content updates outside the editable lifecycle', function (string $status) {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => $status]);
    $before = $listing->fresh()->getAttributes();

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'title' => 'Forbidden title',
    ])->assertHasErrors();

    expect($listing->fresh()->getAttributes())->toBe($before);
})->with(['approved', 'pending_review', 'archived']);

test('does not save content when the combined update exceeds the gallery limit', function () {
    Storage::fake('r2');
    Queue::fake();
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => 'draft']);
    ListingMedia::factory()->count(5)->for($listing)->create(['disk' => 'r2']);
    $before = $listing->fresh()->getAttributes();
    $sourceImage = UploadedFile::fake()->image('extra.png');

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'title' => 'Do not save this title',
        'image_files' => [[
            'filename' => 'extra.png',
            'content_base64' => base64_encode((string) file_get_contents($sourceImage->getPathname())),
        ]],
    ])->assertHasErrors(['Validation failed: A product may have no more than five gallery images.']);

    expect($listing->fresh()->getAttributes())->toBe($before)
        ->and($listing->media()->count())->toBe(5);
});

test('keeps slugs unique when changing a draft title', function () {
    $seller = SellerProfile::factory()->create();
    Listing::factory()->create(['slug' => 'updated-product']);
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => 'draft']);

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'title' => 'Updated Product',
    ])->assertHasNoErrors();

    expect($listing->fresh()->slug)->toBe('updated-product-2');
});

test('refreshes seo using the saved product content and explicit overrides', function (array $changes, string $expectedTitle, string $expectedDescription) {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'title' => 'Original product',
        'short_description' => 'Original summary',
        'description' => '<p>Original full description</p>',
        'meta_title' => 'Custom SEO title',
        'meta_description' => 'Custom SEO description',
    ]);

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        ...$changes,
    ])->assertHasNoErrors()->assertSee(['"meta_title":', '"meta_description":']);

    $listing->refresh();
    expect($listing->meta_title)->toBe($expectedTitle)
        ->and($listing->meta_description)->toBe($expectedDescription);
})->with([
    'title change uses saved summary' => [['title' => 'New product'], 'New product', 'Original summary'],
    'summary change' => [['short_description' => '<p>New &amp; improved</p>'], 'Custom SEO title', 'New & improved'],
    'description change uses saved summary' => [['description' => 'New full description'], 'Custom SEO title', 'Original summary'],
    'clear summary uses saved description' => [['short_description' => null], 'Custom SEO title', 'Original full description'],
    'clear both descriptions uses title' => [['short_description' => null, 'description' => null], 'Custom SEO title', 'Original product'],
    'explicit overrides' => [['title' => 'New product', 'meta_title' => 'Chosen title', 'meta_description' => 'Chosen description'], 'Chosen title', 'Chosen description'],
    'seo only update' => [['meta_title' => 'Chosen title', 'meta_description' => 'Chosen description'], 'Chosen title', 'Chosen description'],
    'explicit regeneration' => [['meta_title' => null, 'meta_description' => null], 'Original product', 'Original summary'],
    'unrelated update preserves custom seo' => [['warranty' => 'Two years'], 'Custom SEO title', 'Custom SEO description'],
    'unchanged source preserves custom seo' => [['title' => 'Original product'], 'Custom SEO title', 'Custom SEO description'],
]);

test('fills missing seo metadata on an unrelated draft update', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'title' => 'Portable Workstation',
        'short_description' => null,
        'description' => '<p>Fast &amp; light</p>',
        'meta_title' => null,
        'meta_description' => '',
    ]);

    MarketplaceServer::actingAs($seller->user)->tool(UpdateDraftProductTool::class, [
        'listing_id' => $listing->id,
        'warranty' => 'Two years',
    ])->assertHasNoErrors();

    expect($listing->fresh()->meta_title)->toBe('Portable Workstation')
        ->and($listing->fresh()->meta_description)->toBe('Fast & light');
});
