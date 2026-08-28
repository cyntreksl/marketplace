<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('home', absolute: false).'?verified=1');
});

test('vendor is redirected to the seller portal after email verification', function () {
    $vendor = User::factory()->unverified()->create();
    $vendor->roles()->attach(Role::factory()->create(['name' => Role::BusinessSeller]));

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $vendor->id, 'hash' => sha1($vendor->email)],
    );

    $this->actingAs($vendor)
        ->withSession(['url.intended' => route('cart.show')])
        ->get($verificationUrl)
        ->assertRedirect(route('seller.listings.index', ['verified' => 1], absolute: false));

    expect($vendor->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('buyer returns to their intended page after email verification', function () {
    $buyer = User::factory()->unverified()->create();
    $buyer->roles()->attach(Role::factory()->create(['name' => Role::Buyer]));

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $buyer->id, 'hash' => sha1($buyer->email)],
    );

    $this->actingAs($buyer)
        ->withSession(['url.intended' => route('cart.show')])
        ->get($verificationUrl)
        ->assertRedirect(route('cart.show'));

    expect($buyer->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('buyer without an intended page returns to the storefront after email verification', function () {
    $buyer = User::factory()->unverified()->create();
    $buyer->roles()->attach(Role::factory()->create(['name' => Role::Buyer]));

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $buyer->id, 'hash' => sha1($buyer->email)],
    );

    $this->actingAs($buyer)
        ->get($verificationUrl)
        ->assertRedirect(route('home', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to storefront from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('home', absolute: false));
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('home', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
