<?php

use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function userManagementAdmin(string $role = Role::Admin): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => $role, 'label' => 'Operations']));

    return $admin;
}

test('admin can browse paginate and filter every account type', function () {
    $admin = userManagementAdmin();
    $buyerRole = Role::factory()->create(['name' => Role::Buyer, 'label' => 'Buyer']);
    $sellerRole = Role::factory()->create(['name' => Role::BusinessSeller, 'label' => 'Business seller']);
    $matchingUser = User::factory()->create([
        'name' => 'Export Seller',
        'created_at' => '2026-09-10 12:00:00',
    ]);
    $matchingUser->roles()->attach([$buyerRole->id, $sellerRole->id]);
    SellerProfile::factory()->for($matchingUser)->create(['store_name' => 'Export Store']);

    User::factory()->count(20)->create(['created_at' => '2026-08-01 12:00:00']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', [
            'search' => 'Export Store',
            'account_type' => 'seller',
            'active' => 'active',
            'verification' => 'verified',
            'created_from' => '2026-09-10',
            'created_to' => '2026-09-10',
            'sort' => 'name',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/users/index')
            ->where('users.total', 1)
            ->where('users.data.0.id', $matchingUser->id)
            ->where('users.data.0.account_types', ['Seller', 'Buyer'])
            ->where('users.data.0.seller_profile.store_name', 'Export Store')
            ->where('filters.created_from', '2026-09-10')
            ->has('exportColumns', 13));

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('users.total', 22)
            ->has('users.data', 20));
});

test('unassigned and inactive unverified users remain visible in the all users page', function () {
    $user = User::factory()->unverified()->create(['is_active' => false]);

    $this->actingAs(userManagementAdmin())
        ->get(route('admin.users.index', ['active' => 'inactive', 'verification' => 'unverified']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $user->id)
            ->where('users.data.0.account_types', ['Unassigned']));
});

test('user filters validate date ranges and supported values', function () {
    $admin = userManagementAdmin();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['account_type' => 'customer']))
        ->assertSessionHasErrors('account_type');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['created_from' => '2026-09-11', 'created_to' => '2026-09-10']))
        ->assertSessionHasErrors('created_to');
});

test('only operations admins can access all users', function (string $actor) {
    $user = match ($actor) {
        'admin' => userManagementAdmin(),
        'super_admin' => userManagementAdmin(Role::SuperAdmin),
        'finance_admin' => userManagementAdmin(Role::FinanceAdmin),
        default => User::factory()->create(),
    };

    $response = $this->actingAs($user)->get(route('admin.users.index'));

    if (in_array($actor, ['admin', 'super_admin'], true)) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with(['admin', 'super_admin', 'finance_admin', 'buyer']);
