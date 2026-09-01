<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\SellerProfile;
use App\Services\ListingImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function validListingImagePayload(Category $category, array $overrides = []): array
{
    return [
        'category_id' => $category->id,
        'title' => 'Professional mirrorless camera',
        'description' => '<p>A carefully maintained camera with all original accessories.</p>',
        'condition' => 'used',
        'listing_type' => 'buy_now',
        'location' => 'Colombo',
        'stock_quantity' => 1,
        'price' => '125000.00',
        'images' => [UploadedFile::fake()->image('camera.jpg', 1600, 1200)],
        'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1200]],
        ...$overrides,
    ];
}

function exifOrientedListingImage(): UploadedFile
{
    $baseImage = UploadedFile::fake()->image('base.jpg', 1200, 1600);
    $binary = file_get_contents($baseImage->getRealPath());
    $tiff = "II\x2A\x00\x08\x00\x00\x00"
        ."\x01\x00"
        ."\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00"
        ."\x00\x00\x00\x00";
    $exif = "Exif\x00\x00".$tiff;
    $appOne = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    return UploadedFile::fake()->createWithContent(
        'oriented.jpg',
        substr((string) $binary, 0, 2).$appOne.substr((string) $binary, 2),
    );
}

test('new listing photos are synchronously cropped to a canonical webp image', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), validListingImagePayload($category))
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $media = Listing::query()->sole()->media()->sole();
    $canonical = Storage::disk('public')->get($media->path);
    $source = Storage::disk('public')->get($media->source_path);
    $canonicalSize = getimagesizefromstring($canonical);
    $sourceSize = getimagesizefromstring($source);

    expect($media->path)->toEndWith('/main.webp')
        ->and($media->source_path)->toEndWith('/source.webp')
        ->and($media->crop_x)->toBe(0)
        ->and($media->crop_y)->toBe(0)
        ->and($media->crop_width)->toBe(1600)
        ->and($media->crop_height)->toBe(1200)
        ->and($media->variant_version)->not->toBeNull()
        ->and($media->processing_status)->toBe('ready')
        ->and($canonicalSize)->not->toBeFalse()
        ->and($canonicalSize[0])->toBe(1200)
        ->and($canonicalSize[1])->toBe(900)
        ->and($canonicalSize['mime'])->toBe('image/webp')
        ->and($sourceSize)->not->toBeFalse()
        ->and($sourceSize['mime'])->toBe('image/webp');
});

test('listing photos accept the reduced eight hundred by six hundred minimum', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), validListingImagePayload($category, [
            'images' => [UploadedFile::fake()->image('minimum.jpg', 800, 600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 800, 'height' => 600]],
        ]))
        ->assertRedirect(route('seller.listings.index', absolute: false))
        ->assertSessionHasNoErrors();

    expect(Listing::query()->sole()->media()->sole()->crop_width)->toBe(800)
        ->and(Listing::query()->sole()->media()->sole()->crop_height)->toBe(600);
});

test('listing image validation rejects invalid dimensions and crop bounds', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), validListingImagePayload($category, [
            'images' => [UploadedFile::fake()->image('small.jpg', 799, 599)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 800, 'height' => 600]],
        ]))
        ->assertSessionHasErrors('images.0');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), validListingImagePayload($category, [
            'image_crops' => [['x' => 100, 'y' => 0, 'width' => 1600, 'height' => 1200]],
        ]))
        ->assertSessionHasErrors('image_crops');

    expect(Listing::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('exif orientation is applied before crop bounds are validated', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), validListingImagePayload($category, [
            'images' => [exifOrientedListingImage()],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1200]],
        ]))
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $media = Listing::query()->sole()->media()->sole();
    $dimensions = getimagesizefromstring(Storage::disk('public')->get($media->path));

    expect($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBe(1200)
        ->and($dimensions[1])->toBe(900);
});

test('new image files are removed when the surrounding transaction rolls back', function () {
    Storage::fake('public');
    $listing = Listing::factory()->create();

    expect(fn () => DB::transaction(function () use ($listing): void {
        app(ListingImageService::class)->store(
            $listing,
            UploadedFile::fake()->image('rollback.jpg', 1600, 1200),
            ['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1200],
            0,
            true,
        );

        throw new RuntimeException('Force rollback.');
    }))->toThrow(RuntimeException::class, 'Force rollback.');

    expect($listing->media()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('a seller cannot upload photos to another sellers listing', function () {
    Storage::fake('public');
    $listing = Listing::factory()->create(['status' => 'draft']);
    $otherSeller = SellerProfile::factory()->create();
    $category = Category::query()->findOrFail($listing->category_id);

    $this->actingAs($otherSeller->user)
        ->put(
            route('seller.listings.update', $listing),
            validListingImagePayload($category),
        )
        ->assertForbidden();

    expect($listing->media()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('a listing cannot exceed five photos across saved and new media', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'category_id' => $category->id,
        'status' => 'draft',
    ]);
    ListingMedia::factory()->count(5)->for($listing)->sequence(
        ['sort_order' => 0],
        ['sort_order' => 1],
        ['sort_order' => 2],
        ['sort_order' => 3],
        ['sort_order' => 4],
    )->create();

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), validListingImagePayload($category))
        ->assertSessionHasErrors('images');

    expect($listing->media()->count())->toBe(5);
});

test('variant generation is dimensionally exact idempotent and creates og only for the cover', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $payload = validListingImagePayload($category, [
        'images' => [
            UploadedFile::fake()->image('cover.jpg', 1600, 1200),
            UploadedFile::fake()->image('detail.png', 1600, 1200),
        ],
        'image_crops' => [
            ['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1200],
            ['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1200],
        ],
    ]);

    $this->actingAs($seller->user)->post(route('seller.listings.store'), $payload);

    [$cover, $detail] = Listing::query()->sole()->media()->orderBy('sort_order')->get()->all();
    $images = app(ListingImageService::class);
    $images->generateVariants($cover->id, (string) $cover->variant_version, true);
    $firstVariantPaths = $cover->refresh()->variants;
    $images->generateVariants($cover->id, (string) $cover->variant_version, true);
    $images->generateVariants($detail->id, (string) $detail->variant_version, false);

    $cover->refresh();
    $detail->refresh();

    expect($cover->variants)->toBe($firstVariantPaths)
        ->and($cover->processing_status)->toBe('ready')
        ->and($cover->variants)->toHaveKeys(['thumbnail', 'card', 'card_2x', 'open_graph'])
        ->and($detail->processing_status)->toBe('ready')
        ->and($detail->variants)->toHaveKeys(['thumbnail', 'card', 'card_2x'])
        ->and($detail->variants)->not->toHaveKey('open_graph');

    foreach ([
        'thumbnail' => [240, 180, 'image/webp'],
        'card' => [480, 360, 'image/webp'],
        'card_2x' => [960, 720, 'image/webp'],
        'open_graph' => [1200, 630, 'image/jpeg'],
    ] as $variant => [$width, $height, $mime]) {
        $dimensions = getimagesizefromstring(Storage::disk('public')->get($cover->variants[$variant]));

        expect($dimensions)->not->toBeFalse()
            ->and($dimensions[0])->toBe($width)
            ->and($dimensions[1])->toBe($height)
            ->and($dimensions['mime'])->toBe($mime);
    }
});

test('legacy and failed media variants fall back to their existing canonical url', function () {
    Storage::fake('public');
    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'path' => 'listings/legacy.jpg',
        'variants' => null,
        'processing_status' => 'failed',
    ]);

    expect($media->urlForVariant('thumbnail'))->toBe($media->url)
        ->and($media->urlForVariant('card'))->toBe($media->url)
        ->and($media->urlForVariant('open_graph'))->toBe($media->url);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.media.0.url', $media->url)
            ->where('listing.media.0.thumbnailUrl', $media->url)
            ->where('listing.media.0.cardUrl', $media->url)
            ->where('listing.media.0.card2xUrl', $media->url));
});

test('listing seo metadata is rendered in initial html and safely escapes seller content', function () {
    Storage::fake('public');
    $listing = Listing::factory()->create([
        'title' => 'Camera <script>alert("x")</script>',
        'slug' => 'safe-camera',
        'description' => '<p>Sharp photos &amp; original box.</p>',
    ]);
    ListingMedia::factory()->for($listing)->create([
        'path' => 'listings/safe-camera/main.webp',
        'variant_version' => fake()->uuid(),
        'variants' => ['open_graph' => 'listings/safe-camera/open-graph-1200x630.jpg'],
        'processing_status' => 'ready',
    ]);

    $response = $this->get(route('listings.show', $listing->slug));

    $response->assertOk()
        ->assertSee('property="og:type" content="product"', escape: false)
        ->assertSee('property="og:image:width" content="1200"', escape: false)
        ->assertSee('property="og:image:height" content="630"', escape: false)
        ->assertSee('name="twitter:card" content="summary_large_image"', escape: false)
        ->assertSee('Camera &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', escape: false)
        ->assertDontSee('<script>alert("x")</script>', escape: false);
});
