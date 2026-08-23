<?php

namespace App\Services;

use App\Models\SellerLedgerEntry;
use App\Models\SellerProfile;

class SellerLedgerService
{
    public function availableBalance(SellerProfile $seller): string
    {
        return (string) SellerLedgerEntry::query()
            ->where('seller_profile_id', $seller->id)
            ->where('status', 'available')
            ->selectRaw('coalesce(sum(amount), 0) as total')
            ->value('total');
    }
}
