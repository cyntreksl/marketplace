<?php

test('the storefront home shares the ProDeals.lk identity', function () {
    config()->set('app.name', 'ProDeals.lk');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('name', 'ProDeals.lk')
            ->has('featuredListings.data')
            ->has('categories'));
});
