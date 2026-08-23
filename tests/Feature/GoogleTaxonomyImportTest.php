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

test('taxonomy activation refuses a version that drops an existing local mapping', function () {
    $admin = superAdmin();
    Category::factory()->create(['google_product_category_id' => 99]);
    $file = UploadedFile::fake()->createWithContent('taxonomy.txt', '1 - Electronics');

    $this->actingAs($admin)->post(route('admin.taxonomy.store'), ['taxonomy_file' => $file, 'version' => 'incomplete', 'locale' => 'en'])->assertRedirect();
    $taxonomy = GoogleProductTaxonomyVersion::query()->sole();

    $this->actingAs($admin)->post(route('admin.taxonomy.activate', $taxonomy), ['reason' => 'Attempt activation'])->assertSessionHasErrors('taxonomy');
});
