<?php

namespace App\Services;

use App\AuctionOfferStatus;
use App\AuctionStatus;
use App\Contracts\Repositories\AuctionRepository;
use App\Models\CustomerOrder;
use Illuminate\Support\Facades\DB;

class AuctionPaymentCompletionService
{
    public function __construct(private readonly AuctionRepository $auctions) {}

    public function complete(CustomerOrder $order): void
    {
        if ($order->auction_offer_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $offer = $this->auctions->offerForUpdate($order->auction_offer_id);
            if ($offer->status === AuctionOfferStatus::Paid) {
                return;
            }

            $this->auctions->saveOffer($offer, [
                'status' => AuctionOfferStatus::Paid,
                'paid_at' => now(),
            ]);
            $this->auctions->save($offer->auction, [
                'status' => AuctionStatus::Sold,
                'payment_due_at' => null,
            ]);
        }, attempts: 3);
    }
}
