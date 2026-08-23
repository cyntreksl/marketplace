<?php

namespace App\Contracts\Repositories;

use App\Models\Auction;

interface AuctionRepository
{
    public function findForUpdate(int $auctionId): Auction;
}
