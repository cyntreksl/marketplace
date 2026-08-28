<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Models\Brand;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceModerationService
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
        private readonly CatalogRepository $catalog,
    ) {}

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

            if ($status === 'approved') {
                $this->approveTypedBrand($actor, $listing, $reason);
            }

            $listing->forceFill([
                'status' => $status,
                'moderation_reason' => $reason,
                'approved_at' => $status === 'approved' ? now() : $listing->approved_at,
            ])->save();
            $this->auditLogs->record($actor, 'listing.status_updated', $listing, $before, $listing->getAttributes(), $reason);

            return $listing;
        });
    }

    private function approveTypedBrand(User $actor, Listing $listing, string $reason): void
    {
        $brandName = $listing->brand_name;

        if ($brandName === null) {
            return;
        }

        $brand = $this->catalog->findBrandByNameForUpdate($brandName);

        if ($brand === null) {
            $brand = new Brand([
                'name' => $brandName,
                'slug' => $this->uniqueBrandSlug($brandName),
            ]);
            $this->catalog->saveBrand($brand);
            $this->auditLogs->record($actor, 'brand.created_from_listing_approval', $brand, after: $brand->getAttributes(), reason: $reason);
        }

        $listing->forceFill([
            'brand_id' => $brand->id,
            'brand_name' => null,
        ]);
    }

    private function uniqueBrandSlug(string $brandName): string
    {
        $base = Str::slug($brandName) ?: 'brand';
        $slug = $base;
        $counter = 2;

        while (Brand::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
