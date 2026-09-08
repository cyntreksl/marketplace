<?php

use App\AuctionOfferStatus;
use App\AuctionStatus;
use App\Models\Auction;
use App\Models\AuctionOffer;
use App\Models\Bid;
use App\Models\CustomerOrder;
use App\Models\User;
use App\Services\AuctionOrderService;
use App\Services\AuctionPaymentCompletionService;

test('accepting an auction offer creates a stripe-only order outside the cart', function () {
    $buyer = User::factory()->create();
    $auction = Auction::factory()->create([
        'status' => AuctionStatus::OfferPending,
        'quantity' => 2,
        'current_price' => 12500,
    ]);
    $bid = Bid::factory()->create([
        'auction_id' => $auction->id,
        'buyer_id' => $buyer->id,
        'amount' => 12500,
    ]);
    $offer = AuctionOffer::factory()->create([
        'auction_id' => $auction->id,
        'bid_id' => $bid->id,
        'buyer_id' => $buyer->id,
        'quantity' => 2,
        'unit_price' => 12500,
        'status' => AuctionOfferStatus::Offered,
        'expires_at' => now()->addHours(24),
    ]);

    $address = [
        'recipient_name' => 'Auction Buyer',
        'address_line_one' => '1 Main Street',
        'address_line_two' => null,
        'city' => 'Colombo',
        'postal_code' => '00100',
        'phone' => '0771234567',
    ];
    $order = app(AuctionOrderService::class)->accept($buyer, $offer->id, $address);

    expect($order->auction_offer_id)->toBe($offer->id)
        ->and($order->subtotal)->toBe('25000.00')
        ->and($order->shipping_total)->toBe('600.00')
        ->and($order->total)->toBe('25600.00')
        ->and($order->payments->sole()->method)->toBe('stripe')
        ->and($order->sellerOrders->sole()->items->sole()->pricing_tier)->toBe('auction')
        ->and($offer->refresh()->status)->toBe(AuctionOfferStatus::Accepted);

    expect(app(AuctionOrderService::class)->accept($buyer, $offer->id, $address)->id)->toBe($order->id);
});

test('auction offer endpoint rejects cash on delivery and bank transfer', function (string $method) {
    $buyer = User::factory()->create();
    $auction = Auction::factory()->create(['status' => AuctionStatus::OfferPending]);
    $bid = Bid::factory()->create(['auction_id' => $auction->id, 'buyer_id' => $buyer->id]);
    $offer = AuctionOffer::factory()->create([
        'auction_id' => $auction->id,
        'bid_id' => $bid->id,
        'buyer_id' => $buyer->id,
    ]);

    $this->actingAs($buyer)->post(route('buyer.auction-offers.accept', $offer), [
        'payment_method' => $method,
        'recipient_name' => 'Buyer',
        'address_line_one' => '1 Main Street',
        'city' => 'Colombo',
        'phone' => '0771234567',
    ])->assertSessionHasErrors('payment_method');
})->with(['cod', 'bank_transfer']);

test('confirmed auction payment completes the offer and auction idempotently', function () {
    $buyer = User::factory()->create();
    $auction = Auction::factory()->create(['status' => AuctionStatus::OfferPending]);
    $bid = Bid::factory()->create(['auction_id' => $auction->id, 'buyer_id' => $buyer->id]);
    $offer = AuctionOffer::factory()->create([
        'auction_id' => $auction->id,
        'bid_id' => $bid->id,
        'buyer_id' => $buyer->id,
        'status' => AuctionOfferStatus::Accepted,
    ]);
    $order = CustomerOrder::factory()->create([
        'buyer_id' => $buyer->id,
        'auction_offer_id' => $offer->id,
        'status' => 'paid',
    ]);

    app(AuctionPaymentCompletionService::class)->complete($order);
    $paidAt = $offer->refresh()->paid_at;
    app(AuctionPaymentCompletionService::class)->complete($order);

    expect($offer->refresh()->status)->toBe(AuctionOfferStatus::Paid)
        ->and($offer->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($auction->refresh()->status)->toBe(AuctionStatus::Sold)
        ->and($auction->payment_due_at)->toBeNull();
});
