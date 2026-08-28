<?php

use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an admin can upload schedule and replace homepage promotion artwork', function () {
    Storage::fake('public');
    config()->set('filesystems.media', 'public');
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    $this->actingAs($admin)->post(route('admin.homepage.promotions.store'), [
        'title' => 'Seasonal marketplace collection',
        'image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
        'link_url' => '/listings?sort=newest',
        'placement' => 'hero',
        'sort_order' => 1,
        'is_active' => true,
        'starts_at' => now()->subHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
        'reason' => 'Launch the current campaign artwork',
    ])->assertRedirect(route('admin.homepage.index'));

    $promotion = Promotion::query()->sole();
    Storage::disk('public')->assertExists($promotion->image_path);
    $oldPath = $promotion->image_path;

    $this->actingAs($admin)->post(route('admin.homepage.promotions.update', $promotion), [
        '_method' => 'PATCH',
        'title' => 'Updated marketplace collection',
        'image' => UploadedFile::fake()->image('replacement.jpg', 1600, 900),
        'link_url' => '/listings',
        'placement' => 'hero',
        'sort_order' => 2,
        'is_active' => true,
        'starts_at' => '',
        'ends_at' => '',
        'reason' => 'Replace artwork with the approved crop',
    ])->assertRedirect(route('admin.homepage.index'));

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($promotion->refresh()->image_path);
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'promotion.updated']);
});

test('the storefront returns only active currently scheduled promotions in display order', function () {
    Promotion::factory()->create(['title' => 'Second', 'placement' => 'hero', 'sort_order' => 2]);
    Promotion::factory()->create(['title' => 'First', 'placement' => 'hero', 'sort_order' => 1]);
    Promotion::factory()->create(['title' => 'Future', 'placement' => 'hero', 'starts_at' => now()->addDay()]);
    Promotion::factory()->create(['title' => 'Expired', 'placement' => 'hero', 'ends_at' => now()->subDay()]);
    Promotion::factory()->create(['title' => 'Inactive', 'placement' => 'hero', 'is_active' => false]);

    $promotions = $this->get(route('home'))->assertOk()->inertiaProps('promotions.hero');

    expect($promotions)->toHaveCount(1)
        ->and($promotions[0]['title'])->toBe('First');
});

test('non operations users cannot manage promotions', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create())->post(route('admin.homepage.promotions.store'), [
        'title' => 'Unauthorized',
        'image' => UploadedFile::fake()->image('hero.jpg'),
        'placement' => 'hero',
        'sort_order' => 0,
        'is_active' => true,
        'reason' => 'Attempt unauthorized promotion write',
    ])->assertForbidden();
});
