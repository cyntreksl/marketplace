<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function createDraft(User $seller, array $attributes): Listing
    {
        $profile = $this->sellerProfileFor($seller);

        return DB::transaction(function () use ($seller, $profile, $attributes): Listing {
            $category = Category::query()->whereKey((int) $attributes['category_id'])->firstOrFail();
            $listing = new Listing([
                'seller_profile_id' => $profile->id,
                'category_id' => $category->id,
                'brand_id' => $attributes['brand_id'] ?? null,
                'title' => $attributes['title'],
                'slug' => $this->uniqueSlug($attributes['title']),
                'description' => $attributes['description'],
                'condition' => $attributes['condition'],
                'listing_type' => $attributes['listing_type'],
                'status' => 'draft',
                'location' => $attributes['location'],
                'warranty' => $attributes['warranty'] ?? null,
                'stock_quantity' => $attributes['listing_type'] === 'buy_now' ? $attributes['stock_quantity'] : 1,
                'price' => $attributes['listing_type'] === 'buy_now' ? $attributes['price'] : null,
                'commission_percentage' => $category->commission_percentage,
            ]);
            $this->listings->save($listing);

            if ($listing->listing_type === 'auction') {
                Auction::query()->create([
                    'listing_id' => $listing->id,
                    'status' => 'draft',
                    'starting_price' => $attributes['starting_price'],
                    'reserve_price' => $attributes['reserve_price'] ?? null,
                    'minimum_increment' => $attributes['minimum_increment'],
                    'current_price' => $attributes['starting_price'],
                    'starts_at' => $attributes['starts_at'],
                    'ends_at' => $attributes['ends_at'],
                ]);
            }

            $this->auditLogs->record($seller, 'listing.draft_created', $listing, after: $listing->getAttributes());

            return $listing;
        });
    }

    public function submit(User $seller, int $listingId): Listing
    {
        $profile = $this->sellerProfileFor($seller);

        if ($profile->status !== 'approved' && $profile->status !== 'active') {
            throw new AuthorizationException('Your seller account must be approved before you can submit listings.');
        }

        return DB::transaction(function () use ($seller, $profile, $listingId): Listing {
            $listing = $this->listings->findForSellerOrFail($profile, $listingId);

            if (! in_array($listing->status, ['draft', 'changes_requested', 'rejected'], true)) {
                throw new AuthorizationException('Only draft or returned listings can be submitted.');
            }

            $before = $listing->getAttributes();
            $listing->forceFill(['status' => 'pending_review', 'submitted_at' => now(), 'moderation_reason' => null]);
            $this->listings->save($listing);
            $this->auditLogs->record($seller, 'listing.submitted', $listing, $before, $listing->getAttributes());

            return $listing;
        });
    }

    private function sellerProfileFor(User $seller): SellerProfile
    {
        return $seller->sellerProfile()->firstOrFail();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        $counter = 2;

        while (Listing::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
