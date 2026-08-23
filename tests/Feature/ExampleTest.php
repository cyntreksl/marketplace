<?php

test('guests can visit the storefront home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->has('featuredListings.data', 0)
            ->has('categories', 0));
});
