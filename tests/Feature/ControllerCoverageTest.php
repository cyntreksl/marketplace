<?php

use App\Models\Auction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;

test('a buyer can view an empty cart and order history', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('cart.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/cart')
            ->has('cart.items', 0));

    $this->actingAs($buyer)
        ->get(route('buyer.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyer/orders/index')
            ->has('orders.data', 0));
});

test('a seller can access each workspace screen', function () {
    $seller = SellerProfile::factory()->create();
    Category::factory()->create();
    Brand::factory()->create();

    $this->actingAs($seller->user)
        ->get(route('seller.onboarding.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('seller/onboarding'));

    $this->actingAs($seller->user)
        ->get(route('seller.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/listings/index')
            ->where('sellerStatus', 'approved'));

    $this->actingAs($seller->user)
        ->get(route('seller.listings.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/listings/create')
            ->missing('categories')
            ->has('brands', 1));

    $this->actingAs($seller->user)
        ->get(route('seller.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/orders/index')
            ->has('orders.data', 0));

    $this->actingAs($seller->user)
        ->get(route('seller.wallet.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('seller/wallet')
            ->where('availableBalance', '0')
            ->has('entries.data', 0)
            ->has('payouts', 0));
});

test('an operations admin can view dashboard and moderation queues', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create([
        'name' => Role::Admin,
        'label' => 'Administrator',
    ]));

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->has('metrics'));

    $this->actingAs($admin)
        ->get(route('admin.sellers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/sellers/index')
            ->has('sellers.data', 0));

    $this->actingAs($admin)
        ->get(route('admin.listings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/listings/index')
            ->has('listings.data', 0));
});

test('a buyer receives validation feedback when an auction bid cannot be accepted', function () {
    $auction = Auction::factory()->create()->load('listing.sellerProfile.user');
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->post(route('auctions.bids.store', $auction), ['maximum_amount' => '12000.00'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($auction->listing->sellerProfile->user)
        ->post(route('auctions.bids.store', $auction), ['maximum_amount' => '12500.00'])
        ->assertRedirect()
        ->assertSessionHasErrors('maximum_amount');
});
