<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function createDraft(User $seller, array $attributes): Listing
    {
        $profile = $this->sellerProfileFor($seller);
        $submitForReview = (bool) ($attributes['submit_for_review'] ?? false);

        if ($submitForReview) {
            $this->ensureCanSubmit($profile);
        }

        return DB::transaction(function () use ($seller, $profile, $attributes, $submitForReview): Listing {
            $category = $this->catalog->selectableCategory((int) $attributes['category_id']);
            $listing = new Listing([
                'seller_profile_id' => $profile->id,
                'category_id' => $category->id,
                'brand_id' => $attributes['brand_id'] ?? null,
                'brand_name' => $attributes['brand_name'] ?? null,
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
            $this->storeImages($listing, $attributes['images']);

            $this->syncAuction($listing, $attributes);

            $this->auditLogs->record($seller, 'listing.draft_created', $listing, after: $listing->getAttributes());

            return $submitForReview ? $this->submitListing($seller, $listing) : $listing;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraft(User $seller, Listing $listing, array $attributes): Listing
    {
        $profile = $this->sellerProfileFor($seller);
        $submitForReview = (bool) ($attributes['submit_for_review'] ?? false);

        if ($submitForReview) {
            $this->ensureCanSubmit($profile);
        }

        return DB::transaction(function () use ($seller, $profile, $listing, $attributes, $submitForReview): Listing {
            $listing = $this->listings->findForSellerOrFail($profile, $listing->id);

            if (! in_array($listing->status, ['draft', 'changes_requested', 'rejected'], true)) {
                throw new AuthorizationException('Only drafts and returned listings can be edited.');
            }

            $category = $this->catalog->selectableCategory((int) $attributes['category_id']);
            $before = $listing->getAttributes();
            $listing->forceFill([
                'category_id' => $category->id,
                'brand_id' => $attributes['brand_id'] ?? null,
                'brand_name' => $attributes['brand_name'] ?? null,
                'title' => $attributes['title'],
                'slug' => $listing->title === $attributes['title'] ? $listing->slug : $this->uniqueSlug($attributes['title'], $listing->id),
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
            $this->syncAuction($listing, $attributes);

            if ($attributes['images'] ?? []) {
                $this->storeImages($listing, $attributes['images']);
            }

            $this->auditLogs->record($seller, 'listing.draft_updated', $listing, $before, $listing->getAttributes());

            return $submitForReview ? $this->submitListing($seller, $listing) : $listing;
        });
    }

    public function submit(User $seller, int $listingId): Listing
    {
        $profile = $this->sellerProfileFor($seller);

        $this->ensureCanSubmit($profile);

        return DB::transaction(function () use ($seller, $profile, $listingId): Listing {
            $listing = $this->listings->findForSellerOrFail($profile, $listingId);

            return $this->submitListing($seller, $listing);
        });
    }

    private function ensureCanSubmit(SellerProfile $profile): void
    {
        if ($profile->status !== 'approved' && $profile->status !== 'active') {
            throw new AuthorizationException('Your seller account must be approved before you can submit listings.');
        }
    }

    private function submitListing(User $seller, Listing $listing): Listing
    {
        if (! in_array($listing->status, ['draft', 'changes_requested', 'rejected'], true)) {
            throw new AuthorizationException('Only draft or returned listings can be submitted.');
        }

        $this->catalog->selectableCategory((int) $listing->category_id);

        $before = $listing->getAttributes();
        $listing->forceFill(['status' => 'pending_review', 'submitted_at' => now(), 'moderation_reason' => null]);
        $this->listings->save($listing);
        $this->auditLogs->record($seller, 'listing.submitted', $listing, $before, $listing->getAttributes());

        return $listing;
    }

    private function sellerProfileFor(User $seller): SellerProfile
    {
        return $seller->sellerProfile()->firstOrFail();
    }

    /** @param array<int, UploadedFile> $images */
    private function storeImages(Listing $listing, array $images): void
    {
        $sortOrder = (int) $listing->media()->max('sort_order') + 1;

        foreach ($images as $image) {
            $listing->media()->create([
                'disk' => 'public',
                'path' => $image->store("listings/{$listing->id}", 'public'),
                'type' => 'image',
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function syncAuction(Listing $listing, array $attributes): void
    {
        if ($listing->listing_type !== 'auction') {
            $listing->auction()->delete();

            return;
        }

        $listing->auction()->updateOrCreate([], [
            'status' => 'draft',
            'starting_price' => $attributes['starting_price'],
            'reserve_price' => $attributes['reserve_price'] ?? null,
            'minimum_increment' => $attributes['minimum_increment'],
            'current_price' => $attributes['starting_price'],
            'starts_at' => $attributes['starts_at'],
            'ends_at' => $attributes['ends_at'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $exceptListingId = null): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        $counter = 2;

        while (Listing::query()->where('slug', $slug)->when($exceptListingId, fn ($query, int $listingId) => $query->whereKeyNot($listingId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
