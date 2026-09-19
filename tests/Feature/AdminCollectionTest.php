<?php

use App\Models\Collection;
use App\Models\Listing;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function actingAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    return $admin;
}

test('an admin can view the collections list and a collection detail page, including archived ones', function () {
    $admin = actingAdmin();
    $collection = Collection::factory()->create(['name' => 'Kids']);
    $archived = Collection::factory()->create(['name' => 'Old Season']);
    $archived->delete();

    $this->actingAs($admin)->get(route('admin.collections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/catalog/collections/index')
            ->has('collections.data', 2 + 5));

    $this->actingAs($admin)->get(route('admin.collections.show', $collection))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/catalog/collections/show')
            ->where('collection.name', 'Kids'));

    $this->actingAs($admin)->get(route('admin.collections.show', $archived->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('collection.name', 'Old Season')
            ->where('collection.deleted_at', fn (?string $value): bool => $value !== null));
});

test('an admin can create a manual collection with curated products in order', function () {
    $admin = actingAdmin();
    $listings = Listing::factory()->count(2)->create();

    $this->actingAs($admin)->post(route('admin.collections.store'), [
        'type' => 'manual',
        'name' => "Women's",
        'is_active' => true,
        'show_on_homepage_tile' => false,
        'show_on_homepage_grid' => false,
        'show_in_navigation' => false,
        'sort_order' => 0,
        'listing_ids' => $listings->reverse()->pluck('id')->all(),
        'reason' => 'Launch the new curated collection',
    ])->assertRedirect();

    $collection = Collection::query()->where('type', 'manual')->sole();
    expect($collection->type->value)->toBe('manual')
        ->and($collection->listings()->pluck('listings.id')->all())->toBe($listings->reverse()->pluck('id')->all());
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'collection.created']);
});

test('creating a rule type collection from the admin panel is rejected', function () {
    $admin = actingAdmin();

    $this->actingAs($admin)->post(route('admin.collections.store'), [
        'type' => 'rule',
        'name' => 'Ad hoc rule collection',
        'is_active' => true,
        'show_on_homepage_tile' => false,
        'show_on_homepage_grid' => false,
        'show_in_navigation' => false,
        'sort_order' => 0,
        'reason' => 'Attempt to add a new rule collection',
    ])->assertInvalid(['type']);

    expect(Collection::query()->where('name', 'Ad hoc rule collection')->exists())->toBeFalse();
});

test('non operations users cannot manage collections', function () {
    $this->actingAs(User::factory()->create())->post(route('admin.collections.store'), [
        'type' => 'manual',
        'name' => 'Unauthorized',
        'is_active' => true,
        'show_on_homepage_tile' => false,
        'show_on_homepage_grid' => false,
        'show_in_navigation' => false,
        'sort_order' => 0,
        'listing_ids' => [],
        'reason' => 'Attempt unauthorized collection write',
    ])->assertForbidden();
});

test('updating a manual collection re-syncs its curated products', function () {
    $admin = actingAdmin();
    $collection = Collection::factory()->create(['name' => 'Kitchen']);
    $kept = Listing::factory()->create();
    $removed = Listing::factory()->create();
    $collection->listings()->sync([$kept->id => ['position' => 0], $removed->id => ['position' => 1]]);
    $added = Listing::factory()->create();

    $this->actingAs($admin)->patch(route('admin.collections.update', $collection), [
        'name' => 'Kitchen & Dining',
        'is_active' => true,
        'show_on_homepage_tile' => false,
        'show_on_homepage_grid' => false,
        'show_in_navigation' => false,
        'sort_order' => 0,
        'listing_ids' => [$added->id, $kept->id],
        'reason' => 'Refresh the curated product list',
    ])->assertRedirect();

    $collection->refresh();
    expect($collection->listings()->pluck('listings.id')->all())->toBe([$added->id, $kept->id]);
});

test('renaming a collection slug records a storefront redirect', function () {
    $admin = actingAdmin();
    $collection = Collection::factory()->create(['name' => 'Toys', 'slug' => 'toys']);
    $listing = Listing::factory()->create();
    $collection->listings()->attach($listing, ['position' => 0]);

    $this->actingAs($admin)->patch(route('admin.collections.update', $collection), [
        'name' => 'Toys & Games',
        'slug' => 'toys-and-games',
        'is_active' => true,
        'show_on_homepage_tile' => false,
        'show_on_homepage_grid' => false,
        'show_in_navigation' => false,
        'sort_order' => 0,
        'listing_ids' => [$listing->id],
        'reason' => 'Rename the collection for clarity',
    ])->assertRedirect();

    $this->assertDatabaseHas('seo_redirects', [
        'source_path' => '/collections/toys',
        'destination_path' => '/collections/toys-and-games',
    ]);
});

test('an admin can upload and remove a collection vertical image, and the storefront falls back to the square tile image', function () {
    Storage::fake('public');
    config()->set('filesystems.media', 'public');
    $admin = actingAdmin();
    $collection = Collection::factory()->create(['name' => 'Kids']);

    $this->actingAs($admin)->post(route('admin.collections.vertical_image.store', $collection), [
        'image' => UploadedFile::fake()->image('kids-vertical.jpg', 900, 1600),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 900, 'height' => 1600],
        'reason' => 'Add the More Collections portrait tile',
    ])->assertRedirect();

    $collection->refresh();
    expect($collection->vertical_image_path)->not->toBeNull()
        ->and($collection->vertical_image_disk)->toBe('public')
        ->and(getimagesizefromstring(Storage::disk('public')->get($collection->vertical_image_path)))->toMatchArray([900, 1600]);
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'collection.vertical_image_updated']);

    $verticalPath = $collection->vertical_image_path;

    $this->actingAs($admin)->delete(route('admin.collections.vertical_image.destroy', $collection), [
        'reason' => 'Remove the portrait tile',
    ])->assertRedirect();

    expect($collection->refresh()->vertical_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($verticalPath);
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'collection.vertical_image_removed']);
});

test('uploading a collection vertical image with the wrong crop ratio is rejected', function () {
    $admin = actingAdmin();
    $collection = Collection::factory()->create(['name' => 'Kids']);

    $this->actingAs($admin)->post(route('admin.collections.vertical_image.store', $collection), [
        'image' => UploadedFile::fake()->image('kids-vertical.jpg', 1200, 1200),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1200],
        'reason' => 'Attempt a square crop for a portrait tile',
    ])->assertSessionHasErrors('crop');
});

test('collection artwork changes regenerate an uncropped 1200x630 open graph image', function () {
    Storage::fake('public');
    config(['filesystems.media' => 'public']);
    $admin = actingAdmin();
    $collection = Collection::factory()->create(['slug' => 'women']);

    $this->actingAs($admin)->post(route('admin.collections.banner_image.store', $collection), [
        'image' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 500],
        'reason' => 'Add the approved collection banner',
    ])->assertRedirect(route('admin.collections.show', $collection));

    $firstPath = $collection->refresh()->open_graph_image_path;
    expect($firstPath)->toStartWith("collections/{$collection->id}/open-graph/")->toEndWith('.jpg');
    Storage::disk('public')->assertExists($firstPath);
    expect(getimagesizefromstring(Storage::disk('public')->get($firstPath)))->toMatchArray([0 => 1200, 1 => 630]);

    $head = implode('', $this->get('/collections/women')->assertOk()->inertiaProps('head'));
    expect($head)->toContain('property="og:image" content="'.e($collection->openGraphImageUrl()).'"')
        ->toContain('property="og:image:width" content="1200"')
        ->toContain('property="og:image:height" content="630"');

    $this->actingAs($admin)->delete(route('admin.collections.banner_image.destroy', $collection), [
        'reason' => 'Remove the collection banner',
    ])->assertRedirect(route('admin.collections.show', $collection));

    expect($collection->refresh()->open_graph_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($firstPath);
});
