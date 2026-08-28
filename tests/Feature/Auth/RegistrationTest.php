<?php

use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/register'),
        );
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('seller registration screen can be rendered', function () {
    $response = $this->get(route('seller.register'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/seller-register')
            ->has('passwordRules'),
        );
});

test('new sellers can register with their store details', function () {
    $response = $this->withHeader('X-Inertia', 'true')->post(route('register.store'), [
        'registration_type' => 'seller',
        'name' => 'Seller Owner',
        'email' => 'seller@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'seller_type' => 'business',
        'store_name' => 'Seller Devices',
        'phone' => '0771234567',
        'accept_terms' => 'on',
    ]);

    $seller = User::query()->where('email', 'seller@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($seller);
    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('seller.listings.create', absolute: false));

    $this->withoutHeader('X-Inertia')->get(route('seller.listings.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/listings/create'),
        );

    expect(SellerProfile::query()->where('user_id', $seller->id)->firstOrFail())
        ->store_name->toBe('Seller Devices')
        ->pickup_address->toBeNull()
        ->return_address->toBeNull()
        ->bank_account_name->toBeNull()
        ->bank_account_details->toBeNull()
        ->status->toBe('pending_review');

    expect($seller->roles()->pluck('name')->all())->toContain(Role::BusinessSeller);
});
