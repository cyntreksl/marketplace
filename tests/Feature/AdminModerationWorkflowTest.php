<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Listing;
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

test('a buyer cannot access operational moderation queues', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.sellers.index'))->assertForbidden();
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
