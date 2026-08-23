<?php

namespace App\Repositories;

use App\Contracts\Repositories\AuctionRepository;
use App\Models\Auction;

class EloquentAuctionRepository implements AuctionRepository
{
    public function findForUpdate(int $auctionId): Auction
    {
        return Auction::query()
            ->with(['listing.sellerProfile', 'bids'])
            ->lockForUpdate()
            ->findOrFail($auctionId);
    }
}
