<?php

use App\Models\Listing;
use Inertia\Testing\AssertableInertia as Assert;

test('homepage product grids receive up to three desktop rows of listings', function () {
    Listing::factory()->count(20)->create([
        'is_featured' => true,
    ]);
    Listing::factory()->count(20)->create([
        'price' => '1000.00',
        'sale_price' => '800.00',
        'is_best_offer' => true,
    ]);
    Listing::factory()->count(20)->create([
        'is_new_arrival' => true,
    ]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('featuredDeals', 18)
        ->has('bestOffers', 18)
        ->has('newArrivals', 18));
});
