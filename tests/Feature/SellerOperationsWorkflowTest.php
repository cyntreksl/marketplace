<?php

use App\Models\Cart;
use App\Models\Listing;
use App\Models\SellerLedgerEntry;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;

function createPaidSellerOrder(SellerProfile $seller): SellerOrder
{
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['seller_profile_id' => $seller->id, 'price' => 12500]);
    $cart = Cart::factory()->create(['buyer_id' => $buyer->id]);
    $cart->items()->create(['listing_id' => $listing->id, 'quantity' => 1]);

    test()->actingAs($buyer)->post(route('checkout.store'), [
        'recipient_name' => 'Buyer Name',
        'address_line_one' => '10 Galle Road',
        'city' => 'Colombo',
        'phone' => '0771234567',
    ])->assertRedirect();

    test()->actingAs($buyer)->post(route('checkout.payment.store'), [
        'payment_method' => 'cod',
    ])->assertRedirect();

    test()->actingAs($buyer)->post(route('checkout.review.store'), checkoutReviewData())->assertRedirect();

    return $seller->sellerOrders()->sole();
}

test('a seller can mark a paid order ready to ship and receive a manual tracking number', function () {
    $seller = SellerProfile::factory()->create();
    $sellerOrder = createPaidSellerOrder($seller);

    $this->actingAs($seller->user)->post(route('seller.orders.ready', $sellerOrder), ['courier_name' => 'City Express'])->assertRedirect();

    expect($sellerOrder->refresh()->status)->toBe('ready_to_ship')
        ->and($sellerOrder->shipment->courier_name)->toBe('City Express')
        ->and($sellerOrder->shipment->tracking_number)->toStartWith('MAN-');
});

test('a seller can request only their available wallet balance', function () {
    $seller = SellerProfile::factory()->create();
    SellerLedgerEntry::query()->create(['seller_profile_id' => $seller->id, 'type' => 'sale_credit', 'status' => 'available', 'amount' => 7000, 'reason' => 'Completed sale']);

    $this->actingAs($seller->user)->post(route('seller.wallet.payouts.store'), ['amount' => 6000])->assertRedirect();
    $this->actingAs($seller->user)->post(route('seller.wallet.payouts.store'), ['amount' => 8000])->assertSessionHasErrors('amount');

    expect($seller->payoutRequests)->toHaveCount(1);
});
