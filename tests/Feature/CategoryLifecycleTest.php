<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function categoryAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    return $admin;
}

test('an admin can create replace and remove optional category artwork', function () {
    Storage::fake('public');
    config()->set('filesystems.media', 'public');
    $admin = categoryAdmin();

    $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'commission_percentage' => 8,
        'return_window_days' => 7,
        'cod_enabled' => true,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('electronics.jpg', 800, 800),
        'reason' => 'Add approved category artwork',
    ])->assertRedirectContains('/admin/catalog/categories?category=');

    $category = Category::query()->sole();
    $originalPath = $category->image_path;

    expect($originalPath)->not->toBeNull();
    Storage::disk('public')->assertExists($originalPath);

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('replacement.webp', 1000, 1000),
        'reason' => 'Use the final approved category crop',
    ])->assertRedirect(route('admin.categories.index', ['category' => $category->id]));

    $replacementPath = $category->refresh()->image_path;
    expect($replacementPath)->not->toBe($originalPath);
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($replacementPath);

    $this->actingAs($admin)->delete(route('admin.categories.image.destroy', $category), [
        'reason' => 'Remove artwork pending brand approval',
    ])->assertRedirect(route('admin.categories.index', ['category' => $category->id]));

    expect($category->refresh()->image_path)->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $category->id)->pluck('action')->all())
        ->toContain('category.created', 'category.image_updated', 'category.image_removed');
    Storage::disk('public')->assertMissing($replacementPath);
});

test('category artwork only accepts supported images and authorized admins', function () {
    Storage::fake('public');
    $category = Category::factory()->create();
    $admin = categoryAdmin();

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->create('category.svg', 20, 'image/svg+xml'),
        'reason' => 'Attempt unsupported artwork type',
    ])->assertSessionHasErrors('image');

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->create('category.jpg', 5121, 'image/jpeg'),
        'reason' => 'Attempt artwork above the upload limit',
    ])->assertSessionHasErrors('image');

    $buyer = User::factory()->create();
    $this->actingAs($buyer)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg'),
        'reason' => 'Attempt unauthorized artwork update',
    ])->assertForbidden();
    $this->actingAs($buyer)->patch(route('admin.categories.activation.update', $category), [
        'is_active' => false,
        'reason' => 'Attempt unauthorized activation change',
    ])->assertForbidden();
});

test('archiving and restoring a category preserves its artwork', function () {
    Storage::fake('public');
    config()->set('filesystems.media', 'public');
    Storage::disk('public')->put('categories/preserved/artwork.webp', 'artwork');

    $admin = categoryAdmin();
    $superAdmin = User::factory()->create();
    $superAdmin->roles()->attach(Role::factory()->create(['name' => Role::SuperAdmin, 'label' => 'Super administrator']));
    $category = Category::factory()->create(['image_path' => 'categories/preserved/artwork.webp']);

    $this->actingAs($admin)->delete(route('admin.categories.destroy', $category), [
        'reason' => 'Archive this category without discarding approved artwork',
    ])->assertRedirect();

    expect($category->fresh()->image_path)->toBe('categories/preserved/artwork.webp');
    Storage::disk('public')->assertExists('categories/preserved/artwork.webp');

    $this->actingAs($superAdmin)->post(route('admin.categories.restore', $category->id), [
        'reason' => 'Restore the category with its approved artwork',
    ])->assertRedirect();

    expect($category->fresh()->trashed())->toBeFalse()
        ->and($category->image_path)->toBe('categories/preserved/artwork.webp');
    Storage::disk('public')->assertExists('categories/preserved/artwork.webp');
});

test('the metadata endpoint cannot bypass subtree activation', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create();

    $this->actingAs($admin)->patch(route('admin.categories.update', $category), [
        'name' => 'Updated category',
        'slug' => 'updated-category',
        'commission_percentage' => 10,
        'return_window_days' => 14,
        'cod_enabled' => true,
        'is_active' => false,
        'reason' => 'Update metadata without changing category availability',
    ])->assertRedirect();

    expect($category->refresh()->name)->toBe('Updated category')
        ->and($category->is_active)->toBeTrue();
});

test('category activation changes complete subtrees and escalates through inactive ancestors', function () {
    $admin = categoryAdmin();
    $root = Category::factory()->create(['name' => 'Root']);
    $mid = Category::factory()->create(['parent_id' => $root->id, 'name' => 'Mid']);
    $leaf = Category::factory()->create(['parent_id' => $mid->id, 'name' => 'Leaf']);
    $sibling = Category::factory()->create(['parent_id' => $root->id, 'name' => 'Sibling']);

    $this->actingAs($admin)->patch(route('admin.categories.activation.update', $mid), [
        'is_active' => false,
        'reason' => 'Temporarily pause this category branch',
    ])->assertRedirect(route('admin.categories.index', ['category' => $mid->id]));

    expect($root->refresh()->is_active)->toBeTrue()
        ->and($mid->refresh()->is_active)->toBeFalse()
        ->and($leaf->refresh()->is_active)->toBeFalse()
        ->and($sibling->refresh()->is_active)->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.categories.activation.update', $root), [
        'is_active' => false,
        'reason' => 'Pause the complete department tree',
    ])->assertRedirect(route('admin.categories.index', ['category' => $root->id]));

    expect(Category::query()->where('is_active', false)->count())->toBe(4);

    $this->actingAs($admin)->patch(route('admin.categories.activation.update', $leaf), [
        'is_active' => true,
        'reason' => 'Restore the department and every branch',
    ])->assertRedirect(route('admin.categories.index', ['category' => $leaf->id]));

    expect(Category::query()->where('is_active', true)->count())->toBe(4)
        ->and(AuditLog::query()->where('action', 'category.subtree_activated')->value('after'))
        ->toMatchArray(['activation_root_id' => $root->id, 'affected_categories' => 4]);
});

test('inactive category listings disappear from every public storefront entry point', function () {
    $admin = categoryAdmin();
    $root = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_popular' => true,
        'homepage_order' => 1,
    ]);
    $child = Category::factory()->create([
        'parent_id' => $root->id,
        'name' => 'Computers',
        'slug' => 'electronics-computers',
    ]);
    $listing = Listing::factory()->create([
        'category_id' => $child->id,
        'is_best_offer' => true,
        'is_new_arrival' => true,
        'price' => '1000.00',
        'sale_price' => '800.00',
    ]);

    $this->actingAs($admin)->patch(route('admin.categories.activation.update', $root), [
        'is_active' => false,
        'reason' => 'Hide this department during catalog review',
    ])->assertRedirect();

    $browse = $this->get(route('listings.index'))->assertOk();
    expect(collect($browse->inertiaProps('listings.data'))->pluck('id'))->not->toContain($listing->id)
        ->and($browse->inertiaProps('categories'))->toBeEmpty();

    $this->get(route('listings.index', ['category' => $root->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('categoryContext', null)->where('listings.total', 0));
    $this->get(route('listings.show', $listing->slug))->assertNotFound();
    $this->getJson(route('listings.recent', ['ids' => [$listing->id]]))->assertJsonCount(0, 'listings');

    $home = $this->get(route('home'))->assertOk();
    expect($home->inertiaProps('popularCategories'))->toBeEmpty()
        ->and(collect($home->inertiaProps('bestOffers'))->pluck('id'))->not->toContain($listing->id)
        ->and(collect($home->inertiaProps('newArrivals'))->pluck('id'))->not->toContain($listing->id);

    $this->actingAs($admin)->patch(route('admin.categories.activation.update', $root), [
        'is_active' => true,
        'reason' => 'Restore the reviewed department tree',
    ])->assertRedirect();

    $this->get(route('listings.show', $listing->slug))->assertOk();
    expect(collect($this->get(route('home'))->inertiaProps('popularCategories'))->pluck('id'))->toContain($root->id);
});

test('storefront category payloads expose optional artwork on home and category pages', function () {
    Storage::fake('public');
    config()->set('filesystems.media', 'public');
    Storage::disk('public')->put('categories/root/root.webp', 'root');
    Storage::disk('public')->put('categories/child/child.webp', 'child');

    $root = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'image_path' => 'categories/root/root.webp',
        'is_popular' => true,
    ]);
    $child = Category::factory()->create([
        'parent_id' => $root->id,
        'name' => 'Computers',
        'slug' => 'electronics-computers',
        'image_path' => 'categories/child/child.webp',
    ]);

    $home = $this->get(route('home'))->assertOk();
    expect($home->inertiaProps('popularCategories.0.image_url'))->toBe(Storage::disk('public')->url($root->image_path));

    $browse = $this->get(route('listings.index', ['category' => $root->slug]))->assertOk();
    expect($browse->inertiaProps('categoryContext.current.image_url'))->toBe(Storage::disk('public')->url($root->image_path))
        ->and($browse->inertiaProps('categoryContext.children.0.id'))->toBe($child->id)
        ->and($browse->inertiaProps('categoryContext.children.0.image_url'))->toBe(Storage::disk('public')->url($child->image_path));
});
