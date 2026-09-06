<?php

use App\Mcp\Servers\MarketplaceServer;
use App\Mcp\Tools\UpdateDraftProductTool;
use App\Models\Listing;
use App\Models\SellerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('adds a generated image file to an existing draft and stores it on r2', function () {
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
        ->and($media->disk)->toBe('r2')
        ->and($media->crop_x)->toBe(200)
        ->and($media->crop_y)->toBe(0)
        ->and($media->crop_width)->toBe(800)
        ->and($media->crop_height)->toBe(800);

    Storage::disk('r2')->assertExists([$media->source_path, $media->path]);
});

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

test('exports an image upload schema for mcp discovery', function () {
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
        ])
        ->and($array['annotations'])->toMatchArray([
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'openWorldHint' => true,
        ]);
});
