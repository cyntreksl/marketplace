<?php

use App\Models\AuditLog;
use App\Models\MarketplaceSetting;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function featureSettingsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create([
        'name' => Role::Admin,
        'label' => 'Operations',
    ]));

    return $admin;
}

test('review feature flags are disabled by default and exposed to inertia', function (): void {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('reviewFlags.product', false)
        ->where('reviewFlags.seller', false));

    $product = MarketplaceSetting::query()->where('key', 'reviews.product.enabled')->firstOrFail();
    $seller = MarketplaceSetting::query()->where('key', 'reviews.seller.enabled')->firstOrFail();

    expect($product->value)->toBeFalse()
        ->and($product->group)->toBe('reviews')
        ->and($seller->value)->toBeFalse()
        ->and($seller->group)->toBe('reviews');
});

test('review feature flag migration preserves an existing operator choice', function (): void {
    $product = MarketplaceSetting::query()->where('key', 'reviews.product.enabled')->firstOrFail();
    $product->update(['value' => true]);

    $migration = require database_path('migrations/2026_09_10_053740_add_review_feature_flags_to_marketplace_settings.php');
    $migration->up();

    expect(MarketplaceSetting::query()->where('key', 'reviews.product.enabled')->firstOrFail()->value)
        ->toBeTrue();
});

test('admins can manage independent review flags with an audit trail', function (): void {
    $admin = featureSettingsAdmin();

    $this->actingAs($admin)
        ->get(route('admin.features.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/features/index')
            ->where('flags.product', false)
            ->where('flags.seller', false));

    $this->actingAs($admin)->put(route('admin.features.update'), [
        'product' => true,
        'seller' => false,
    ])->assertRedirect();

    $product = MarketplaceSetting::query()->where('key', 'reviews.product.enabled')->firstOrFail();
    $seller = MarketplaceSetting::query()->where('key', 'reviews.seller.enabled')->firstOrFail();

    expect($product->value)->toBeTrue()
        ->and($product->group)->toBe('reviews')
        ->and($product->updated_by)->toBe($admin->id)
        ->and($seller->value)->toBeFalse()
        ->and($seller->group)->toBe('reviews')
        ->and($seller->updated_by)->toBe($admin->id)
        ->and(AuditLog::query()->where('action', 'marketplace.feature_flag_updated')->where('actor_id', $admin->id)->count())
        ->toBe(2);
});

test('non admins cannot manage review feature flags', function (): void {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('admin.features.index'))->assertForbidden();
    $this->actingAs($buyer)->put(route('admin.features.update'), [
        'product' => true,
        'seller' => true,
    ])->assertForbidden();
});
