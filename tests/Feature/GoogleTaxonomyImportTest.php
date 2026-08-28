<?php

use App\Models\Category;
use App\Models\GoogleProductTaxonomyVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::factory()->create(['name' => Role::SuperAdmin, 'label' => 'Super administrator']));

    return $user;
}

test('a super admin imports and activates a valid Google taxonomy version', function () {
    $admin = superAdmin();
    $file = UploadedFile::fake()->createWithContent('taxonomy.txt', "1 - Electronics\n2 - Electronics > Computers");

    $this->actingAs($admin)->get(route('admin.taxonomy.index'))->assertOk();
    $this->actingAs($admin)->post(route('admin.taxonomy.store'), ['taxonomy_file' => $file, 'version' => '2026-08', 'locale' => 'en'])->assertRedirect(route('admin.taxonomy.index'));

    $taxonomy = GoogleProductTaxonomyVersion::query()->sole();
    $this->actingAs($admin)->post(route('admin.taxonomy.activate', $taxonomy), ['reason' => 'Use the current official English taxonomy'])->assertRedirect();

    expect($taxonomy->refresh()->is_active)->toBeTrue()->and($taxonomy->nodes)->toHaveCount(2);
});

test('a super admin can archive an unactivated taxonomy import', function () {
    $admin = superAdmin();
    $file = UploadedFile::fake()->createWithContent('taxonomy.txt', '1 - Electronics');
    $this->actingAs($admin)->post(route('admin.taxonomy.store'), ['taxonomy_file' => $file, 'version' => 'draft', 'locale' => 'en'])->assertRedirect();
    $taxonomy = GoogleProductTaxonomyVersion::query()->sole();

    $this->actingAs($admin)->delete(route('admin.taxonomy.destroy', $taxonomy), ['reason' => 'Superseded by corrected import'])->assertRedirect();

    expect($taxonomy->fresh()->trashed())->toBeTrue();
});

test('taxonomy import rejects duplicate category IDs and broken parent paths', function () {
    $admin = superAdmin();
    $duplicate = UploadedFile::fake()->createWithContent('taxonomy.txt', "1 - Electronics\n1 - Electronics > Computers");

    $this->actingAs($admin)->post(route('admin.taxonomy.store'), ['taxonomy_file' => $duplicate, 'version' => 'bad', 'locale' => 'en'])->assertSessionHasErrors('taxonomy_file');

    $brokenParent = UploadedFile::fake()->createWithContent('taxonomy.txt', '2 - Electronics > Computers');
    $this->actingAs($admin)->post(route('admin.taxonomy.store'), ['taxonomy_file' => $brokenParent, 'version' => 'bad-parent', 'locale' => 'en'])->assertSessionHasErrors('taxonomy_file');
});

test('taxonomy activation deactivates a local mapping dropped by the new version', function () {
    $admin = superAdmin();
    $mapped = Category::factory()->create(['google_product_category_id' => 99]);
    $manual = Category::factory()->create(['google_product_category_id' => null]);
    $file = UploadedFile::fake()->createWithContent('taxonomy.txt', '1 - Electronics');

    $this->actingAs($admin)->post(route('admin.taxonomy.store'), ['taxonomy_file' => $file, 'version' => 'incomplete', 'locale' => 'en'])->assertRedirect();
    $taxonomy = GoogleProductTaxonomyVersion::query()->sole();

    $this->actingAs($admin)->post(route('admin.taxonomy.activate', $taxonomy), ['reason' => 'Activate the replacement taxonomy'])->assertRedirect();

    expect($mapped->refresh())
        ->is_active->toBeTrue()
        ->is_taxonomy_available->toBeFalse()
        ->is_selectable->toBeFalse()
        ->and($manual->refresh()->is_active)->toBeTrue()
        ->and($manual->is_taxonomy_available)->toBeNull();
});

test('taxonomy activation preserves an admin deactivated mapped subtree', function () {
    $admin = superAdmin();
    $firstFile = UploadedFile::fake()->createWithContent('first.txt', "1 - Electronics\n2 - Electronics > Computers");

    $this->actingAs($admin)->post(route('admin.taxonomy.store'), [
        'taxonomy_file' => $firstFile,
        'version' => 'first',
        'locale' => 'en',
    ])->assertRedirect();
    $firstTaxonomy = GoogleProductTaxonomyVersion::query()->sole();
    $this->actingAs($admin)->post(route('admin.taxonomy.activate', $firstTaxonomy), [
        'reason' => 'Activate the initial catalog taxonomy',
    ])->assertRedirect();

    $root = Category::query()->where('google_product_category_id', 1)->sole();
    $child = Category::query()->where('google_product_category_id', 2)->sole();
    $this->actingAs($admin)->patch(route('admin.categories.activation.update', $root), [
        'is_active' => false,
        'reason' => 'Pause this mapped department subtree',
    ])->assertRedirect();

    $secondFile = UploadedFile::fake()->createWithContent('second.txt', "# refreshed release\n1 - Electronics\n2 - Electronics > Computers");
    $this->actingAs($admin)->post(route('admin.taxonomy.store'), [
        'taxonomy_file' => $secondFile,
        'version' => 'second',
        'locale' => 'en',
    ])->assertRedirect();
    $secondTaxonomy = GoogleProductTaxonomyVersion::query()->where('version', 'second')->sole();
    $this->actingAs($admin)->post(route('admin.taxonomy.activate', $secondTaxonomy), [
        'reason' => 'Activate the refreshed catalog taxonomy',
    ])->assertRedirect();

    expect($root->refresh())
        ->is_active->toBeFalse()
        ->is_taxonomy_available->toBeTrue()
        ->and($child->refresh()->is_active)->toBeFalse()
        ->and($child->is_taxonomy_available)->toBeTrue();
});
