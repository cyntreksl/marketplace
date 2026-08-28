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

test('an admin can create replace and remove independently cropped category artwork', function () {
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
        'image' => UploadedFile::fake()->image('electronics.jpg', 1000, 1000),
        'image_crop' => ['x' => 100, 'y' => 100, 'width' => 800, 'height' => 800],
        'banner_image' => UploadedFile::fake()->image('electronics-banner.jpg', 1200, 1600),
        'banner_image_crop' => ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1600],
        'reason' => 'Add approved category artwork',
    ])->assertRedirectContains('/admin/catalog/categories?category=');

    $category = Category::query()->sole();
    $originalPath = $category->image_path;
    $originalBannerPath = $category->banner_image_path;

    expect($originalPath)->not->toBeNull()
        ->and($category->image_disk)->toBe('public')
        ->and($originalBannerPath)->not->toBeNull()
        ->and($category->banner_image_disk)->toBe('public')
        ->and(getimagesizefromstring(Storage::disk('public')->get($originalPath)))->toMatchArray([800, 800])
        ->and(image_type_to_mime_type(getimagesizefromstring(Storage::disk('public')->get($originalPath))[2]))->toBe('image/webp')
        ->and(getimagesizefromstring(Storage::disk('public')->get($originalBannerPath)))->toMatchArray([900, 1200])
        ->and(image_type_to_mime_type(getimagesizefromstring(Storage::disk('public')->get($originalBannerPath))[2]))->toBe('image/webp');
    Storage::disk('public')->assertExists([$originalPath, $originalBannerPath]);

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('replacement.webp', 1000, 1000),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 1000],
        'reason' => 'Use the final approved category crop',
    ])->assertRedirect(route('admin.categories.index', ['category' => $category->id]));

    $replacementPath = $category->refresh()->image_path;
    expect($replacementPath)->not->toBe($originalPath);
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($replacementPath);
    Storage::disk('public')->assertExists($originalBannerPath);

    $this->actingAs($admin)->post(route('admin.categories.banner_image.store', $category), [
        'image' => UploadedFile::fake()->image('replacement-banner.webp', 1350, 1800),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1350, 'height' => 1800],
        'reason' => 'Use the final approved homepage banner crop',
    ])->assertRedirect(route('admin.categories.index', ['category' => $category->id]));

    $replacementBannerPath = $category->refresh()->banner_image_path;
    expect($replacementBannerPath)->not->toBe($originalBannerPath)
        ->and(getimagesizefromstring(Storage::disk('public')->get($replacementBannerPath)))->toMatchArray([900, 1200]);
    Storage::disk('public')->assertMissing($originalBannerPath);

    $this->actingAs($admin)->delete(route('admin.categories.image.destroy', $category), [
        'reason' => 'Remove artwork pending brand approval',
    ])->assertRedirect(route('admin.categories.index', ['category' => $category->id]));

    expect($category->refresh()->image_path)->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $category->id)->pluck('action')->all())
        ->toContain('category.created', 'category.image_updated', 'category.image_removed', 'category.banner_image_updated');
    Storage::disk('public')->assertMissing($replacementPath);

    $this->actingAs($admin)->delete(route('admin.categories.banner_image.destroy', $category), [
        'reason' => 'Remove the old homepage banner artwork',
    ])->assertRedirect(route('admin.categories.index', ['category' => $category->id]));

    expect($category->refresh()->banner_image_path)->toBeNull()
        ->and($category->banner_image_disk)->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $category->id)->pluck('action')->all())
        ->toContain('category.banner_image_removed');
    Storage::disk('public')->assertMissing($replacementBannerPath);
});

test('category artwork only accepts supported images and authorized admins', function () {
    Storage::fake('public');
    $category = Category::factory()->create();
    $admin = categoryAdmin();

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->create('category.svg', 20, 'image/svg+xml'),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 800],
        'reason' => 'Attempt unsupported artwork type',
    ])->assertSessionHasErrors('image');

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->create('category.jpg', 5121, 'image/jpeg'),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 800],
        'reason' => 'Attempt artwork above the upload limit',
    ])->assertSessionHasErrors('image');

    $this->actingAs($admin)->post(route('admin.categories.banner_image.store', $category), [
        'image' => UploadedFile::fake()->image('category-banner.jpg', 1200, 1600),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1200],
        'reason' => 'Attempt a square crop for a portrait banner',
    ])->assertSessionHasErrors('crop');

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg', 1000, 1000),
        'reason' => 'Attempt artwork without crop coordinates',
    ])->assertSessionHasErrors('crop');

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg', 1000, 1000),
        'crop' => ['width' => 800, 'height' => 800],
        'reason' => 'Attempt artwork with incomplete crop coordinates',
    ])->assertSessionHasErrors(['crop.x', 'crop.y']);

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg', 799, 800),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 799, 'height' => 799],
        'reason' => 'Attempt artwork below the source size minimum',
    ])->assertSessionHasErrors('image');

    $this->actingAs($admin)->post(route('admin.categories.banner_image.store', $category), [
        'image' => UploadedFile::fake()->image('category-banner.jpg', 6001, 1200),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 900, 'height' => 1200],
        'reason' => 'Attempt artwork above the source dimension limit',
    ])->assertSessionHasErrors('image');

    $buyer = User::factory()->create();
    $this->actingAs($buyer)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg'),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 800],
        'reason' => 'Attempt unauthorized artwork update',
    ])->assertForbidden();
    $this->actingAs($buyer)->post(route('admin.categories.banner_image.store', $category), [
        'image' => UploadedFile::fake()->image('category-banner.jpg', 1200, 1600),
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1600],
        'reason' => 'Attempt unauthorized banner update',
    ])->assertForbidden();
    $this->actingAs($buyer)->patch(route('admin.categories.activation.update', $category), [
        'is_active' => false,
        'reason' => 'Attempt unauthorized activation change',
    ])->assertForbidden();
});

test('category artwork uses the configured media disk and rejects crops outside the source image', function () {
    config([
        'filesystems.media' => 'r2',
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'prodeals-media-production',
        'filesystems.disks.r2.endpoint' => 'https://account-id.r2.cloudflarestorage.com',
        'filesystems.disks.r2.url' => 'https://media.prodeals.lk',
    ]);
    Storage::fake('r2');
    $category = Category::factory()->create();
    $admin = categoryAdmin();

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg', 1000, 1000),
        'crop' => ['x' => 300, 'y' => 300, 'width' => 800, 'height' => 800],
        'reason' => 'Attempt a crop outside the uploaded image',
    ])->assertSessionHasErrors('crop');

    $this->actingAs($admin)->post(route('admin.categories.image.store', $category), [
        'image' => UploadedFile::fake()->image('category.jpg', 1000, 1000),
        'crop' => ['x' => 100, 'y' => 100, 'width' => 800, 'height' => 800],
        'reason' => 'Store the approved square crop on R2',
    ])->assertRedirect();

    $category->refresh();
    expect($category->image_disk)->toBe('r2');
    Storage::disk('r2')->assertExists($category->image_path);
    Storage::forgetDisk('r2');
    expect($category->imageUrl())->toBe('https://media.prodeals.lk/'.$category->image_path);
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
