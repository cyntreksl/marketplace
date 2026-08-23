<?php

use App\Models\AuditLog;
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
