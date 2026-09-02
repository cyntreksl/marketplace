<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an approved seller can create a draft listing and submit it for moderation', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create(['commission_percentage' => 8]);
    $description = '<p>A well cared for <strong>full-frame</strong> camera body.</p><ul><li>Low shutter count</li><li>Original box included</li></ul>';
    $specifications = '<p><strong>Sensor:</strong> 24MP full-frame</p><ul><li>Dual card slots</li></ul>';

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'brand_name' => 'Canon',
            'sku' => 'CANON-R6-001',
            'model' => 'EOS R6 Mark II',
            'title' => 'Canon EOS R6',
            'description' => $description,
            'specifications_text' => $specifications,
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'stock_quantity' => 2,
            'price' => '325000.00',
            'images' => [UploadedFile::fake()->image('camera.jpg', 1600, 1600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $listing = Listing::query()->sole();

    expect($listing->status)->toBe('draft')
        ->and($listing->commission_percentage)->toBe('8.00')
        ->and($listing->model)->toBe('EOS R6 Mark II')
        ->and($listing->specifications)->toBe(['Details' => $specifications])
        ->and($listing->location)->toBeNull()
        ->and($listing->description)->toBe($description)
        ->and($listing->media)->toHaveCount(1);

    Storage::disk('public')->assertExists($listing->media->sole()->path);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.submit'), ['listing_id' => $listing->id])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect($listing->refresh()->status)->toBe('pending_review');
});

test('listing uploads use the configured media disk', function () {
    config([
        'filesystems.media' => 'r2',
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'prodeals-media-production',
        'filesystems.disks.r2.endpoint' => 'https://account-id.r2.cloudflarestorage.com',
        'filesystems.disks.r2.url' => 'https://media.prodeals.lk',
    ]);
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'title' => 'R2 hosted camera',
            'description' => 'A camera whose listing image is stored on the configured media disk.',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'location' => 'Singapore',
            'stock_quantity' => 1,
            'price' => '25000.00',
            'images' => [UploadedFile::fake()->image('camera.jpg', 1600, 1600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $media = Listing::query()->sole()->media()->sole();

    expect($media->disk)->toBe('r2');
    Storage::disk('r2')->assertExists($media->path);
    Storage::forgetDisk('r2');

    expect($media->url)->toBe('https://media.prodeals.lk/'.$media->path)
        ->and($media->toArray()['url'])->toBe($media->url);
});

test('an approved seller can save a typed brand draft or submit it directly for review', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'brand_name' => 'Northstar Optics',
            'title' => 'Northstar binoculars',
            'description' => 'Compact binoculars in excellent condition.',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'stock_quantity' => 1,
            'price' => '18500.00',
            'images' => [UploadedFile::fake()->image('binoculars.jpg', 1600, 1600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $draft = Listing::query()->firstOrFail();

    expect($draft->status)->toBe('draft')
        ->and($draft->brand_id)->toBeNull()
        ->and($draft->brand_name)->toBe('Northstar Optics');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'brand_name' => 'Northstar Optics',
            'sku' => 'NORTHSTAR-TELESCOPE-001',
            'title' => 'Northstar telescope',
            'description' => 'A compact telescope ready for stargazing.',
            'condition' => 'new',
            'listing_type' => 'buy_now',
            'stock_quantity' => 1,
            'price' => '24500.00',
            'images' => [UploadedFile::fake()->image('telescope.jpg', 1600, 1600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
            'submit_for_review' => 1,
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect(Listing::query()->latest('id')->firstOrFail()->status)->toBe('pending_review');
});

test('a seller can revise a returned listing before submitting it again', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create(['commission_percentage' => 10]);
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'changes_requested',
        'moderation_reason' => 'Please clarify the warranty coverage.',
        'specifications' => ['Details' => 'Body only kit'],
    ]);

    $this->actingAs($seller->user)
        ->get(route('seller.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/listings/edit')
            ->where('listing.id', $listing->id)
            ->where('listing.specifications.Details', 'Body only kit'));

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), [
            'category_id' => $category->id,
            'brand_id' => $listing->brand_id,
            'title' => 'Canon EOS R6 with warranty',
            'description' => 'A well cared for full-frame camera body with a six month warranty.',
            'specifications_text' => 'Full-frame sensor with dual card slots',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'location' => 'Colombo',
            'warranty' => 'Six months',
            'stock_quantity' => 2,
            'price' => '325000.00',
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect($listing->refresh())
        ->status->toBe('draft')
        ->title->toBe('Canon EOS R6 with warranty')
        ->specifications->toBe(['Details' => 'Full-frame sensor with dual card slots'])
        ->commission_percentage->toBe('10.00');
});

test('a seller can view their product details', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'title' => 'Draft mirrorless camera',
        'model' => 'X-T5',
        'specifications' => ['Details' => 'Weather sealed body'],
    ]);
    $media = ListingMedia::factory()->for($listing)->create([
        'path' => 'listings/draft-camera/main.webp',
        'sort_order' => 0,
    ]);

    $this->actingAs($seller->user)
        ->get(route('seller.listings.show', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/listings/show')
            ->where('listing.id', $listing->id)
            ->where('listing.title', 'Draft mirrorless camera')
            ->where('listing.model', 'X-T5')
            ->where('listing.specifications.Details', 'Weather sealed body')
            ->where('listing.category.id', $listing->category_id)
            ->where('listing.brand.id', $listing->brand_id)
            ->where('listing.media.0.id', $media->id)
            ->where('listing.media.0.url', $media->url));
});

test('a seller cannot view another sellers product details', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($seller->user)
        ->get(route('seller.listings.show', $listing))
        ->assertForbidden();
});

test('an unapproved seller cannot submit a listing for moderation', function () {
    $seller = SellerProfile::factory()->create(['status' => 'pending_review']);
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => 'draft']);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.submit'), ['listing_id' => $listing->id])
        ->assertForbidden();

    expect($listing->refresh()->status)->toBe('draft');
});

test('an unapproved seller cannot submit a new listing directly for review', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create(['status' => 'pending_review']);
    $category = Category::factory()->create();

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'brand_name' => 'Draft Brand',
            'sku' => 'DRAFT-CAMERA-001',
            'title' => 'Draft only camera',
            'description' => 'This listing cannot be submitted yet.',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'location' => 'Galle',
            'stock_quantity' => 1,
            'price' => '15000.00',
            'images' => [UploadedFile::fake()->image('camera.jpg', 1600, 1600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
            'submit_for_review' => 1,
        ])
        ->assertForbidden();

    expect(Listing::query()->count())->toBe(0);
});

test('a seller can replace a typed brand with a catalog brand while editing a draft', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'status' => 'draft',
        'brand_id' => null,
        'brand_name' => 'Draft brand',
    ]);

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'title' => $listing->title,
            'description' => $listing->description,
            'condition' => $listing->condition,
            'listing_type' => 'buy_now',
            'location' => $listing->location,
            'stock_quantity' => 1,
            'price' => '15000.00',
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect($listing->refresh())
        ->brand_id->toBe($brand->id)
        ->brand_name->toBeNull();
});

test('an unverified seller can create a private listing draft', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()
        ->for(User::factory()->unverified(), 'user')
        ->create(['status' => 'pending_review']);
    $category = Category::factory()->create(['commission_percentage' => 8]);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'title' => 'Canon EOS R6',
            'description' => 'A well cared for full-frame camera body.',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'location' => 'Colombo',
            'stock_quantity' => 2,
            'price' => '325000.00',
            'images' => [UploadedFile::fake()->image('camera.jpg', 1600, 1600)],
            'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1600]],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect(Listing::query()->sole()->status)->toBe('draft');

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings.data', 0));
});

test('a seller removes a listing without orders and it is hidden from the public marketplace', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id]);

    $this->actingAs($seller->user)
        ->delete(route('seller.listings.destroy', $listing))
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $this->assertSoftDeleted($listing);

    $this->get(route('listings.show', $listing->slug))->assertNotFound();
    expect(collect($this->get(route('listings.index'))->inertiaProps('listings.data'))->pluck('id'))
        ->not->toContain($listing->id);
});

test('a seller archives a listing with orders and it is hidden from the public marketplace', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id]);
    OrderItem::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller->user)
        ->delete(route('seller.listings.destroy', $listing))
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect($listing->refresh())
        ->status->toBe('archived')
        ->deleted_at->toBeNull();

    $this->get(route('listings.show', $listing->slug))->assertNotFound();
    expect(collect($this->get(route('listings.index'))->inertiaProps('listings.data'))->pluck('id'))
        ->not->toContain($listing->id);
});

test('a seller cannot remove or archive another sellers listing', function () {
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($seller->user)
        ->delete(route('seller.listings.destroy', $listing))
        ->assertForbidden();

    $this->assertModelExists($listing);
    expect($listing->refresh()->status)->toBe('approved');
});

test('the seller listing page identifies listings that must be archived', function () {
    $seller = SellerProfile::factory()->create();
    $removableListing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'product_type' => 'variant',
        'model' => 'Config-100',
    ]);
    $archivableListing = Listing::factory()->create(['seller_profile_id' => $seller->id]);
    OrderItem::factory()->create(['listing_id' => $archivableListing->id]);

    $listings = collect($this->actingAs($seller->user)
        ->get(route('seller.listings.index'))
        ->inertiaProps('listings.data'))
        ->keyBy('id');

    expect($listings[$removableListing->id]['has_orders'])->toBeFalse()
        ->and($listings[$removableListing->id]['product_type'])->toBe('variant')
        ->and($listings[$removableListing->id]['model'])->toBe('Config-100')
        ->and($listings[$removableListing->id]['brand']['name'])->toBe($removableListing->brand->name)
        ->and($listings[$archivableListing->id]['has_orders'])->toBeTrue();
});
