<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;

function operationsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Operations']));

    return $admin;
}

test('operations admins can create and archive local categories with an audit reason', function () {
    $admin = operationsAdmin();

    $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Wearable technology', 'slug' => 'wearable-technology', 'commission_percentage' => 8,
        'return_window_days' => 7, 'cod_enabled' => true, 'is_active' => true, 'reason' => 'Initial marketplace catalog structure',
    ])->assertRedirectContains('/admin/catalog/categories?category=');

    $category = Category::query()->sole();
    $this->actingAs($admin)->patch(route('admin.categories.update', $category), [
        'name' => 'Wearables', 'slug' => 'wearables', 'commission_percentage' => 9,
        'return_window_days' => 14, 'cod_enabled' => true, 'is_active' => true, 'reason' => 'Aligning with buyer navigation',
    ])->assertRedirect();
    $this->actingAs($admin)->delete(route('admin.categories.destroy', $category), ['reason' => 'Category is being consolidated'])->assertRedirect();

    expect($category->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_id', $category->id)->count())->toBe(3);
});

test('buyers cannot access catalog management', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.categories.index'))->assertForbidden();
});

test('operations admins can browse and maintain brands', function () {
    $admin = operationsAdmin();
    Brand::factory()->create();

    $this->actingAs($admin)->get(route('admin.brands.index'))->assertOk();
    $this->actingAs($admin)->post(route('admin.brands.store'), ['name' => 'Circuit', 'slug' => 'circuit', 'reason' => 'Approved manufacturer catalogue'])->assertRedirect();
    $brand = Brand::query()->where('slug', 'circuit')->sole();
    $this->actingAs($admin)->delete(route('admin.brands.destroy', $brand), ['reason' => 'Duplicate manufacturer entry'])->assertRedirect();

    expect($brand->fresh()->trashed())->toBeTrue();
});

test('a super admin restores an archived catalog record', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->roles()->attach(Role::factory()->create(['name' => Role::SuperAdmin, 'label' => 'Super administrator']));
    $category = Category::factory()->create();
    $category->delete();

    $this->actingAs($superAdmin)->post(route('admin.categories.restore', $category->id), ['reason' => 'The category is required for a live listing'])->assertRedirect();

    expect($category->fresh()->trashed())->toBeFalse();
});
