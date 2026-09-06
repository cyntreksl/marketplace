<?php

namespace App\Services;

use App\Models\SellerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SellerSummaryService
{
    /** @return array{store_name: string, slug: string, logoUrl: ?string, coverUrl: ?string, about: ?string, sellingSince: ?string, productCount: int} */
    public function forSeller(SellerProfile $seller): array
    {
        return [
            'store_name' => $seller->store_name,
            'slug' => $seller->slug,
            'logoUrl' => $seller->logo_path ? Storage::disk(config('filesystems.media'))->url($seller->logo_path) : null,
            'coverUrl' => $seller->cover_path ? Storage::disk(config('filesystems.media'))->url($seller->cover_path) : null,
            'about' => $seller->about,
            'sellingSince' => $seller->approved_at === null ? null : Carbon::parse($seller->approved_at)->format('F Y'),
            'productCount' => (int) $seller->getAttribute('public_product_count'),
        ];
    }
}
