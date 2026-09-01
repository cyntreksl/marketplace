<?php

use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function deliveredItemFor(User $buyer): OrderItem
{
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id]);
    $customerOrder = CustomerOrder::factory()->create(['buyer_id' => $buyer->id]);
    $sellerOrder = SellerOrder::factory()->delivered()->create(['customer_order_id' => $customerOrder->id, 'seller_profile_id' => $seller->id]);

    return OrderItem::factory()->create(['seller_order_id' => $sellerOrder->id, 'listing_id' => $listing->id, 'title' => $listing->title]);
}

test('a buyer can review each delivered order item once', function () {
    $buyer = User::factory()->create();
    $item = deliveredItemFor($buyer);

    $this->actingAs($buyer)->post(route('buyer.reviews.store', $item), [
        'rating' => 5,
        'comment' => 'Exactly as described and delivered carefully.',
    ])->assertRedirect();

    $this->assertDatabaseHas('reviews', ['order_item_id' => $item->id, 'buyer_id' => $buyer->id, 'rating' => 5]);

    $this->actingAs($buyer)->post(route('buyer.reviews.store', $item), [
        'rating' => 4,
        'comment' => 'A duplicate review should not be accepted.',
    ])->assertSessionHasErrors('order_item');
});

test('reviews require valid ratings and ownership of a delivered purchase', function () {
    $buyer = User::factory()->create();
    $item = deliveredItemFor($buyer);

    $this->actingAs($buyer)->post(route('buyer.reviews.store', $item), ['rating' => 6])->assertSessionHasErrors('rating');
    $this->actingAs(User::factory()->create())->post(route('buyer.reviews.store', $item), ['rating' => 5])->assertNotFound();

    $undelivered = OrderItem::factory()->create();
    $this->actingAs($buyer)->post(route('buyer.reviews.store', $undelivered), ['rating' => 5])->assertNotFound();
});

test('verified review aggregates appear on products while the homepage review wall stays removed', function () {
    $buyer = User::factory()->create();
    $item = deliveredItemFor($buyer);
    Review::factory()->create(['order_item_id' => $item->id, 'buyer_id' => $buyer->id, 'seller_profile_id' => $item->sellerOrder->seller_profile_id, 'rating' => 4, 'comment' => 'A dependable marketplace purchase.']);

    $listing = $item->listing;
    $this->get(route('listings.show', $listing->slug))->assertInertia(fn (Assert $page) => $page
        ->where('listing.ratingAverage', 4)
        ->where('listing.reviewCount', 1)
        ->has('reviews', 1));

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->missing('socialProof'));
});
