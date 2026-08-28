<?php

use App\Models\Category;
use App\Models\ListingMedia;
use App\Models\Promotion;
use App\Services\StaticMediaService;
use Illuminate\Support\Facades\Storage;

function configureMediaMigrationDisks(): void
{
    config([
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'prodeals-media-production',
        'filesystems.disks.r2.endpoint' => 'https://account-id.r2.cloudflarestorage.com',
        'filesystems.disks.r2.url' => 'https://media.prodeals.lk',
    ]);
    Storage::fake('public');
    Storage::fake('r2');
}

test('the media migration dry run validates objects without changing files or records', function () {
    configureMediaMigrationDisks();
    $category = Category::factory()->create([
        'image_path' => 'categories/1/image.webp',
        'image_disk' => 'public',
    ]);
    Storage::disk('public')->put($category->image_path, 'category-image');

    $this->artisan('media:migrate-to-r2', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run completed')
        ->assertSuccessful();

    expect($category->fresh()->image_disk)->toBe('public');
    Storage::disk('r2')->assertMissing($category->image_path);
    Storage::disk('r2')->assertDirectoryEmpty('site');
});

test('the media migration copies every runtime image and updates disk ownership idempotently', function () {
    configureMediaMigrationDisks();
    $listingMedia = ListingMedia::factory()->create([
        'disk' => 'public',
        'path' => 'listings/1/main.webp',
        'source_path' => 'listings/1/source.webp',
        'variants' => [
            'thumbnail' => 'listings/1/thumbnail.webp',
            'card' => 'listings/1/card.webp',
        ],
    ]);
    $category = Category::factory()->create([
        'image_path' => 'categories/1/image.webp',
        'image_disk' => 'public',
        'banner_image_path' => 'categories/1/banner.webp',
        'banner_image_disk' => 'public',
    ]);
    $promotion = Promotion::factory()->create(['image_disk' => 'public']);
    $paths = [
        $listingMedia->path,
        $listingMedia->source_path,
        ...array_values($listingMedia->variants),
        $category->image_path,
        $category->banner_image_path,
        $promotion->image_path,
    ];

    foreach ($paths as $path) {
        Storage::disk('public')->put($path, 'source-'.$path);
    }

    $this->artisan('media:migrate-to-r2')
        ->expectsOutputToContain('Media migration completed')
        ->assertSuccessful();

    Storage::disk('r2')->assertExists($paths);
    foreach (StaticMediaService::ASSETS as $asset) {
        Storage::disk('r2')->assertExists('site/'.$asset);
    }

    $category->refresh();
    expect($listingMedia->fresh()->disk)->toBe('r2')
        ->and($category->image_disk)->toBe('r2')
        ->and($category->banner_image_disk)->toBe('r2')
        ->and($promotion->fresh()->image_disk)->toBe('r2');
    Storage::disk('public')->assertExists($paths);

    $this->artisan('media:migrate-to-r2')->assertSuccessful();
    Storage::disk('r2')->assertExists($paths);
});

test('a missing source object fails without moving its database record', function () {
    configureMediaMigrationDisks();
    $listingMedia = ListingMedia::factory()->create([
        'disk' => 'public',
        'path' => 'listings/ready/main.webp',
        'source_path' => 'listings/ready/source.webp',
        'variants' => null,
    ]);
    Storage::disk('public')->put($listingMedia->path, 'main');
    Storage::disk('public')->put($listingMedia->source_path, 'source');
    $category = Category::factory()->create([
        'image_path' => 'categories/missing/image.webp',
        'image_disk' => 'public',
    ]);

    $this->artisan('media:migrate-to-r2')
        ->expectsOutputToContain('Missing media object')
        ->assertFailed();

    expect($listingMedia->fresh()->disk)->toBe('public')
        ->and($category->fresh()->image_disk)->toBe('public');
    Storage::disk('r2')->assertExists([$listingMedia->path, $listingMedia->source_path]);
});
