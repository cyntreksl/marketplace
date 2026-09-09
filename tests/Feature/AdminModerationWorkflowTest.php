<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;

test('an operations admin can approve sellers and listings with an audit trail', function () {
    $admin = User::factory()->create();
    $role = Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']);
    $admin->roles()->attach($role);
    $seller = SellerProfile::factory()->create(['status' => 'pending_review']);
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'status' => 'pending_review', 'approved_at' => null]);

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), ['status' => 'approved', 'reason' => 'Identity and bank details verified'])->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.listings.update', $listing), ['status' => 'approved', 'reason' => 'Listing meets marketplace guidelines'])->assertRedirect();

    expect($seller->refresh()->status)->toBe('approved')
        ->and($listing->refresh()->status)->toBe('approved')
        ->and(AuditLog::query()->count())->toBe(2);
});

test('buyers and sellers cannot access operational moderation queues', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.sellers.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.products.index'))->assertForbidden();

    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)->get(route('admin.listings.index'))->assertForbidden();
    $this->actingAs($seller->user)->get(route('admin.products.index'))->assertForbidden();
});

test('listing reviews default to a paginated pending review queue', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    Listing::factory()
        ->count(21)
        ->recycle([$seller, $category, $brand])
        ->create(['status' => 'pending_review', 'approved_at' => null]);
    Listing::factory()->recycle([$seller, $category, $brand])->create(['status' => 'approved']);

    $this->actingAs($admin)
        ->get(route('admin.listings.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/listings/index')
            ->where('view', 'moderation')
            ->where('filters.status', 'pending_review')
            ->where('listings.current_page', 2)
            ->where('listings.total', 21)
            ->has('listings.data', 1));
});

test('listing reviews can be searched and filtered', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $seller = SellerProfile::factory()->create(['store_name' => 'Precision Audio']);
    $matching = Listing::factory()->for($seller)->create([
        'title' => 'Reference monitor',
        'status' => 'rejected',
        'listing_type' => 'auction',
        'product_type' => 'variant',
        'condition' => 'refurbished',
        'approved_at' => null,
    ]);
    Listing::factory()->for($seller)->create([
        'status' => 'rejected',
        'listing_type' => 'auction',
        'product_type' => 'variant',
        'condition' => 'used',
        'approved_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.listings.index', [
            'search' => 'Precision Audio',
            'status' => 'rejected',
            'listing_type' => 'auction',
            'product_type' => 'variant',
            'condition' => 'refurbished',
            'sort' => 'oldest',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'Precision Audio')
            ->where('filters.status', 'rejected')
            ->where('filters.sort', 'oldest')
            ->where('listings.total', 1)
            ->where('listings.data.0.id', $matching->id));
});

test('all products are paginated and support catalog filters', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    Listing::factory()
        ->count(21)
        ->recycle([$seller, $category, $brand])
        ->create([
            'title' => 'Catalog product',
            'status' => 'approved',
            'listing_type' => 'buy_now',
            'product_type' => 'simple',
            'condition' => 'new',
        ]);
    Listing::factory()->recycle([$seller, $category, $brand])->create([
        'title' => 'Catalog product draft',
        'status' => 'draft',
        'condition' => 'new',
        'approved_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.index', [
            'search' => 'Catalog product',
            'status' => 'approved',
            'listing_type' => 'buy_now',
            'product_type' => 'simple',
            'condition' => 'new',
            'sort' => 'title',
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/listings/index')
            ->where('view', 'all')
            ->where('filters.status', 'approved')
            ->where('listings.current_page', 2)
            ->where('listings.total', 21)
            ->has('listings.data', 1));
});

test('an operations admin can inspect every product detail before moderation', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $listing = Listing::factory()->create([
        'status' => 'pending_review',
        'title' => 'Studio monitor speakers',
        'description' => '<p>Matched pair for accurate monitoring.</p>',
        'specifications' => ['Details' => '<table><tbody><tr><th>Power</th><td>100W</td></tr></tbody></table>'],
        'approved_at' => null,
    ]);
    $media = ListingMedia::factory()->for($listing)->create();

    $this->actingAs($admin)
        ->get(route('admin.listings.show', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/listings/show')
            ->where('listing.id', $listing->id)
            ->where('listing.title', 'Studio monitor speakers')
            ->where('listing.description', '<p>Matched pair for accurate monitoring.</p>')
            ->where('listing.specifications.Details', '<table><tbody><tr><th>Power</th><td>100W</td></tr></tbody></table>')
            ->where('listing.seller_profile.store_name', $listing->sellerProfile->store_name)
            ->where('listing.media.0.id', $media->id)
            ->has('listing.seo_score.checks'));

    $this->actingAs($admin)
        ->get(route('admin.listings.edit', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/listings/edit')
            ->where('listing.id', $listing->id)
            ->where('selectedCategory.id', $listing->category_id)
            ->has('brands'));
});

test('an operations admin can edit complete product details without changing moderation status', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $category = Category::factory()->create(['commission_percentage' => 12]);
    $listing = Listing::factory()->create([
        'status' => 'pending_review',
        'approved_at' => null,
        'is_featured' => true,
        'is_best_seller' => true,
        'is_new_arrival' => true,
    ]);
    ListingMedia::factory()->for($listing)->create();
    $description = '<p>Updated description with a comparison table.</p><table><tbody><tr><th>Finish</th><td>Black</td></tr></tbody></table>';
    $specifications = '<table><thead><tr><th>Feature</th><th>Value</th></tr></thead><tbody><tr><td>Power</td><td>100W</td></tr></tbody></table>';

    $this->actingAs($admin)
        ->put(route('admin.listings.details.update', $listing), [
            'category_id' => $category->id,
            'brand_id' => $listing->brand_id,
            'sku' => 'ADMIN-EDIT-001',
            'title' => 'Updated studio monitor speakers',
            'description' => $description,
            'specifications_text' => $specifications,
            'condition' => 'new',
            'product_type' => 'simple',
            'stock_quantity' => 8,
            'selling_price' => '42000.00',
            'compare_price' => '45000.00',
            'low_stock_threshold' => 2,
            'allow_backorders' => false,
            'is_active' => true,
            'is_featured' => false,
            'is_best_seller' => false,
            'is_new_arrival' => false,
            'variant_options' => [],
            'variants' => [],
            'images' => [],
            'image_crops' => [],
            'removed_media_ids' => [],
        ])
        ->assertRedirect(route('admin.listings.show', $listing, absolute: false))
        ->assertSessionHasNoErrors();

    expect($listing->refresh())
        ->status->toBe('pending_review')
        ->title->toBe('Updated studio monitor speakers')
        ->description->toBe($description)
        ->specifications->toBe(['Details' => $specifications])
        ->commission_percentage->toBe('12.00')
        ->is_featured->toBeTrue()
        ->is_best_seller->toBeTrue()
        ->is_new_arrival->toBeTrue()
        ->and(AuditLog::query()->where('action', 'listing.details_updated_by_admin')->exists())->toBeTrue();
});

test('a buyer cannot view or edit an admin listing review', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['status' => 'pending_review']);

    $this->actingAs($buyer)->get(route('admin.listings.show', $listing))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.listings.edit', $listing))->assertForbidden();
    $this->actingAs($buyer)->put(route('admin.listings.details.update', $listing), [])->assertForbidden();
    $this->actingAs($buyer)->patch(route('admin.listings.merchandising.update', $listing), [])->assertForbidden();
});

test('an operations admin cannot approve a listing that was not submitted for review', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $listing = Listing::factory()->create(['status' => 'draft', 'approved_at' => null]);

    $this->actingAs($admin)
        ->patch(route('admin.listings.update', $listing), ['status' => 'approved', 'reason' => 'Looks good'])
        ->assertSessionHasErrors('status');

    expect($listing->refresh()->status)->toBe('draft');
});

test('approving a typed brand listing creates and attaches the catalog brand', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $listing = Listing::factory()->create([
        'brand_id' => null,
        'brand_name' => 'Northstar Optics',
        'status' => 'pending_review',
        'approved_at' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.listings.update', $listing), ['status' => 'approved', 'reason' => 'Listing and brand details verified'])
        ->assertRedirect();

    $brand = Brand::query()->where('name', 'Northstar Optics')->sole();

    expect($listing->refresh())
        ->status->toBe('approved')
        ->brand_id->toBe($brand->id)
        ->brand_name->toBeNull()
        ->and(AuditLog::query()->where('action', 'brand.created_from_listing_approval')->exists())->toBeTrue();
});

test('approving a typed brand listing reuses an existing catalog brand', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $brand = Brand::factory()->create(['name' => 'Northstar Optics', 'slug' => 'northstar-optics']);
    $listing = Listing::factory()->create([
        'brand_id' => null,
        'brand_name' => 'Northstar Optics',
        'status' => 'pending_review',
        'approved_at' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.listings.update', $listing), ['status' => 'approved', 'reason' => 'Listing verified'])
        ->assertRedirect();

    expect($listing->refresh()->brand_id)->toBe($brand->id)
        ->and(Brand::query()->where('name', 'Northstar Optics')->count())->toBe(1);
});
