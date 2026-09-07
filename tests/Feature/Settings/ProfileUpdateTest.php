<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('unverified users can open profile settings and are prompted to verify', function (string $routeName, string $component) {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('mustVerifyEmail', true)
            ->where('auth.user.email_verified_at', null));
})->with([
    'seller profile' => ['profile.edit', 'settings/profile'],
    'buyer profile' => ['buyer.settings.profile.edit', 'buyer/settings/profile'],
]);

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email_verified_at)->not->toBeNull();
});

test('email address changes are rejected by profile update endpoints', function (string $routeName) {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $response = $this
        ->actingAs($user)
        ->patch(route($routeName), [
            'name' => 'Test User',
            'email' => 'changed@example.com',
        ]);

    $response
        ->assertSessionHasErrors('email');

    expect($user->refresh()->email)->toBe($originalEmail)
        ->and($user->name)->not->toBe('Test User');
})->with([
    'seller portal' => 'profile.update',
    'buyer portal' => 'buyer.settings.profile.update',
]);

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->trashed())->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
