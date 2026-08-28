<?php

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function categoryBrowserAdmin(string $role = Role::Admin): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create([
        'name' => $role,
        'label' => 'Category browser administrator',
    ]));

    return $admin;
}

test('the category page loads roots and can rebuild a seven-level focused trail', function () {
    $admin = categoryBrowserAdmin();
    $root = Category::factory()->create(['name' => 'Root', 'slug' => 'root', 'sort_order' => 2]);
    Category::factory()->create(['name' => 'Earlier root', 'slug' => 'earlier-root', 'sort_order' => 1]);

    $trail = collect([$root]);
    $parent = $root;
    foreach (range(1, 6) as $depth) {
        $parent = Category::factory()->create([
            'parent_id' => $parent->id,
            'name' => "Level {$depth}",
            'slug' => "level-{$depth}",
        ]);
        $trail->push($parent);
    }

    $this->actingAs($admin)
        ->get(route('admin.categories.index', ['category' => $parent->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/catalog/categories')
            ->where('categoryCount', 8)
            ->has('rootCategories', 2)
            ->where('rootCategories.0.name', 'Earlier root')
            ->where('selectedContext.selected.id', $parent->id)
            ->has('selectedContext.trail', 7)
            ->has('selectedContext.columns', 7)
            ->where('selectedContext.trail.0.id', $root->id)
            ->where('selectedContext.trail.6.id', $parent->id));
});

test('child browsing includes every lifecycle state in stable catalog order', function () {
    $admin = categoryBrowserAdmin();
    $root = Category::factory()->create();
    $first = Category::factory()->create([
        'parent_id' => $root->id,
        'name' => 'Alpha',
        'slug' => 'alpha',
        'sort_order' => 1,
        'is_active' => false,
    ]);
    $archived = Category::factory()->create([
        'parent_id' => $root->id,
        'name' => 'Archived',
        'slug' => 'archived',
        'sort_order' => 2,
    ]);
    $archived->delete();
    $last = Category::factory()->create([
        'parent_id' => $root->id,
        'name' => 'Zulu',
        'slug' => 'zulu',
        'sort_order' => 3,
        'is_taxonomy_available' => false,
    ]);
    Category::factory()->create(['parent_id' => $last->id]);

    $this->actingAs($admin)
        ->getJson(route('admin.categories.children', ['parent_id' => $root->id]))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.is_active', false)
        ->assertJsonPath('data.1.id', $archived->id)
        ->assertJsonPath('data.1.deleted_at', fn (?string $value): bool => $value !== null)
        ->assertJsonPath('data.2.id', $last->id)
        ->assertJsonPath('data.2.is_taxonomy_available', false)
        ->assertJsonPath('data.2.has_children', true);
});

test('admin search returns full paths filters lifecycle states and caps results', function () {
    $admin = categoryBrowserAdmin();
    $root = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    $leaf = Category::factory()->create([
        'parent_id' => $root->id,
        'name' => 'Laptops',
        'slug' => 'laptops',
    ]);
    $inactive = Category::factory()->create(['name' => 'Inactive result', 'is_active' => false]);
    $taxonomyUnavailable = Category::factory()->create([
        'name' => 'Taxonomy unavailable result',
        'is_taxonomy_available' => false,
    ]);
    $archived = Category::factory()->create(['name' => 'Archived result']);
    $archived->delete();

    $this->actingAs($admin)
        ->getJson(route('admin.categories.search', ['query' => 'Laptops']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $leaf->id)
        ->assertJsonPath('data.0.path', 'Electronics > Laptops');

    foreach ([
        'storefront_visible' => $leaf->id,
        'admin_active' => $root->id,
        'admin_inactive' => $inactive->id,
        'taxonomy_unavailable' => $taxonomyUnavailable->id,
        'archived' => $archived->id,
    ] as $status => $expectedId) {
        $ids = collect($this->actingAs($admin)
            ->getJson(route('admin.categories.search', ['status' => $status]))
            ->assertOk()
            ->json('data'))
            ->pluck('id');

        expect($ids)->toContain($expectedId);
    }

    Category::factory()->count(55)->sequence(
        fn ($sequence): array => ['name' => "Bounded result {$sequence->index}"],
    )->create();

    $this->actingAs($admin)
        ->getJson(route('admin.categories.search', ['query' => 'Bounded result']))
        ->assertOk()
        ->assertJsonCount(50, 'data');
});

test('parent options exclude archived categories and the selected subtree', function () {
    $admin = categoryBrowserAdmin();
    $root = Category::factory()->create(['name' => 'Root parent']);
    $child = Category::factory()->create(['parent_id' => $root->id, 'name' => 'Child parent']);
    $grandchild = Category::factory()->create(['parent_id' => $child->id, 'name' => 'Grandchild parent']);
    $eligible = Category::factory()->create(['name' => 'Eligible parent']);
    $archived = Category::factory()->create(['name' => 'Archived parent']);
    $archived->delete();

    $ids = collect($this->actingAs($admin)
        ->getJson(route('admin.categories.search', [
            'parent_options' => true,
            'exclude_subtree_id' => $child->id,
        ]))
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($ids)
        ->toContain($root->id, $eligible->id)
        ->not->toContain($child->id, $grandchild->id, $archived->id);
});

test('archived category payload exposes restore capability only to super administrators', function () {
    $admin = categoryBrowserAdmin();
    $superAdmin = categoryBrowserAdmin(Role::SuperAdmin);
    $archived = Category::factory()->create();
    $archived->delete();

    $this->actingAs($admin)
        ->getJson(route('admin.categories.context', $archived->id))
        ->assertOk()
        ->assertJsonPath('data.selected.capabilities.can_restore', false)
        ->assertJsonPath('data.selected.capabilities.can_update', false);

    $this->actingAs($superAdmin)
        ->getJson(route('admin.categories.context', $archived->id))
        ->assertOk()
        ->assertJsonPath('data.selected.capabilities.can_restore', true)
        ->assertJsonPath('data.selected.capabilities.can_archive', false);
});

test('category reparenting rejects self descendants and archived parents', function () {
    $admin = categoryBrowserAdmin();
    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $archived = Category::factory()->create();
    $archived->delete();
    $attributes = [
        'name' => $root->name,
        'slug' => $root->slug,
        'commission_percentage' => 8,
        'return_window_days' => 7,
        'cod_enabled' => true,
        'reason' => 'Attempt an invalid hierarchy update',
    ];

    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $root), [...$attributes, 'parent_id' => $root->id])
        ->assertSessionHasErrors('parent_id');
    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $root), [...$attributes, 'parent_id' => $child->id])
        ->assertSessionHasErrors('parent_id');
    $this->actingAs($admin)
        ->patch(route('admin.categories.update', $root), [...$attributes, 'parent_id' => $archived->id])
        ->assertSessionHasErrors('parent_id');

    expect($root->refresh()->parent_id)->toBeNull();
});

test('category browser endpoints require operations access', function () {
    $buyer = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($buyer)->getJson(route('admin.categories.children'))->assertForbidden();
    $this->actingAs($buyer)->getJson(route('admin.categories.search'))->assertForbidden();
    $this->actingAs($buyer)->getJson(route('admin.categories.context', $category))->assertForbidden();
});
