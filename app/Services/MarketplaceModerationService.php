<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarketplaceModerationService
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function reviewSeller(User $actor, SellerProfile $seller, string $status, string $reason): SellerProfile
    {
        return DB::transaction(function () use ($actor, $seller, $status, $reason): SellerProfile {
            $seller = SellerProfile::query()->lockForUpdate()->findOrFail($seller->id);
            $before = $seller->getAttributes();
            $seller->forceFill([
                'status' => $status,
                'review_reason' => $reason,
                'approved_at' => $status === 'approved' ? now() : $seller->approved_at,
            ])->save();
            $this->auditLogs->record($actor, 'seller.status_updated', $seller, $before, $seller->getAttributes(), $reason);

            return $seller;
        });
    }

    public function reviewListing(User $actor, Listing $listing, string $status, string $reason): Listing
    {
        return DB::transaction(function () use ($actor, $listing, $status, $reason): Listing {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $before = $listing->getAttributes();
            $listing->forceFill([
                'status' => $status,
                'moderation_reason' => $reason,
                'approved_at' => $status === 'approved' ? now() : $listing->approved_at,
            ])->save();
            $this->auditLogs->record($actor, 'listing.status_updated', $listing, $before, $listing->getAttributes(), $reason);

            return $listing;
        });
    }
}
