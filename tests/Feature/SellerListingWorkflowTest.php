<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an approved seller can create a draft listing and submit it for moderation', function () {
    Storage::fake('public');
    $seller = SellerProfile::factory()->create();
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
            'images' => [UploadedFile::fake()->image('camera.jpg')],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $listing = Listing::query()->sole();

    expect($listing->status)->toBe('draft')
        ->and($listing->commission_percentage)->toBe('8.00')
        ->and($listing->media)->toHaveCount(1);

    Storage::disk('public')->assertExists($listing->media->sole()->path);

    $this->actingAs($seller->user)
        ->post(route('seller.listings.submit'), ['listing_id' => $listing->id])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect($listing->refresh()->status)->toBe('pending_review');
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
            'location' => 'Kandy',
            'stock_quantity' => 1,
            'price' => '18500.00',
            'images' => [UploadedFile::fake()->image('binoculars.jpg')],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    $draft = Listing::query()->firstOrFail();

    expect($draft->status)->toBe('draft')
        ->and($draft->brand_id)->toBeNull()
        ->and($draft->brand_name)->toBe('Northstar Optics');

    $this->actingAs($seller->user)
        ->post(route('seller.listings.store'), [
            'category_id' => $category->id,
            'title' => 'Northstar telescope',
            'description' => 'A compact telescope ready for stargazing.',
            'condition' => 'new',
            'listing_type' => 'buy_now',
            'location' => 'Kandy',
            'stock_quantity' => 1,
            'price' => '24500.00',
            'images' => [UploadedFile::fake()->image('telescope.jpg')],
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
    ]);

    $this->actingAs($seller->user)
        ->get(route('seller.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/listings/edit')
            ->where('listing.id', $listing->id));

    $this->actingAs($seller->user)
        ->put(route('seller.listings.update', $listing), [
            'category_id' => $category->id,
            'brand_id' => $listing->brand_id,
            'title' => 'Canon EOS R6 with warranty',
            'description' => 'A well cared for full-frame camera body with a six month warranty.',
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
        ->commission_percentage->toBe('10.00');
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
            'title' => 'Draft only camera',
            'description' => 'This listing cannot be submitted yet.',
            'condition' => 'used',
            'listing_type' => 'buy_now',
            'location' => 'Galle',
            'stock_quantity' => 1,
            'price' => '15000.00',
            'images' => [UploadedFile::fake()->image('camera.jpg')],
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

test('an unverified vendor can create a private listing draft', function () {
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
            'images' => [UploadedFile::fake()->image('camera.jpg')],
        ])
        ->assertRedirect(route('seller.listings.index', absolute: false));

    expect(Listing::query()->sole()->status)->toBe('draft');

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings.data', 0));
});
