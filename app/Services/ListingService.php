<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListingService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
        private readonly AuditLogService $auditLogs,
        private readonly ListingImageService $images,
        private readonly ListingVariantService $variants,
    ) {}

    /**
     * @return array{sellerStatus: string, listings: LengthAwarePaginator<int, Listing>}
     */
    public function sellerIndex(User $seller): array
    {
        $profile = $this->sellerProfileFor($seller);

        return [
            'sellerStatus' => $profile->status,
            'listings' => $this->listings->paginateForSeller($profile),
        ];
    }

    public function sellerProduct(User $seller, int $listingId): Listing
    {
        return $this->listings->findDetailedForSellerOrFail(
            $this->sellerProfileFor($seller),
            $listingId,
        );
    }

    /** @param array<string, mixed> $attributes */
    public function createDraft(User $seller, array $attributes): Listing
    {
        $profile = $this->sellerProfileFor($seller);
        $submitForReview = (bool) ($attributes['submit_for_review'] ?? false);

        if ($submitForReview) {
            $this->ensureCanSubmit($profile);
        }

        return DB::transaction(function () use ($seller, $profile, $attributes, $submitForReview): Listing {
            $listing = new Listing($this->productAttributes($attributes));
            $listing->forceFill([
                'seller_profile_id' => $profile->id,
                'status' => 'draft',
            ]);
            $this->listings->save($listing);
            $this->variants->synchronize($listing, $attributes);
            $this->synchronizeVariantSummary($listing);
            $this->storeImages($listing, $attributes['images'] ?? [], $attributes['image_crops'] ?? []);
            $listing->auction()->delete();

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

            $before = $listing->getAttributes();
            $listing->forceFill([
                ...$this->productAttributes($attributes, $listing),
                'status' => 'draft',
                'is_best_offer' => false,
            ]);
            $this->listings->save($listing);
            $listing->auction()->delete();
            $this->images->remove($listing, array_map('intval', $attributes['removed_media_ids'] ?? []));
            $this->variants->synchronize($listing, $attributes);
            $this->synchronizeVariantSummary($listing);

            if ($attributes['images'] ?? []) {
                $this->storeImages($listing, $attributes['images'], $attributes['image_crops']);
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

    public function removeOrArchive(User $seller, int $listingId): string
    {
        $profile = $this->sellerProfileFor($seller);

        return DB::transaction(function () use ($seller, $profile, $listingId): string {
            $listing = $this->listings->findForSellerOrFail($profile, $listingId, lockForUpdate: true);
            $hasOrders = $listing->orderItems()->exists();
            $before = $listing->getAttributes();

            if ($hasOrders) {
                $listing->forceFill(['status' => 'archived']);
                $this->listings->save($listing);
                $this->auditLogs->record($seller, 'listing.archived', $listing, $before, $listing->getAttributes());

                return 'archived';
            }

            $this->auditLogs->record($seller, 'listing.removed', $listing, $before);
            $this->listings->delete($listing);

            return 'removed';
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

        if ($listing->category_id !== null) {
            $this->catalog->selectableCategory((int) $listing->category_id);
        }

        $this->ensureReadyForReview($listing);

        if ($listing->slug === null && $listing->title !== null) {
            $listing->slug = $this->uniqueSlug($listing->title, $listing->id);
        }

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

    /**
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, array{x: int, y: int, width: int, height: int}>  $crops
     */
    private function storeImages(Listing $listing, array $images, array $crops): void
    {
        $sortOrder = $this->listings->nextMediaSortOrder($listing);
        $isCover = $this->listings->mediaCount($listing) === 0;

        foreach ($images as $index => $image) {
            $this->images->store($listing, $image, $crops[$index], $sortOrder++, $isCover && $index === 0);
        }
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function productAttributes(array $attributes, ?Listing $listing = null): array
    {
        $category = filled($attributes['category_id'] ?? null)
            ? $this->catalog->selectableCategory((int) $attributes['category_id'])
            : null;
        $title = filled($attributes['title'] ?? null) ? (string) $attributes['title'] : null;
        $sellingPrice = filled($attributes['selling_price'] ?? null) ? $attributes['selling_price'] : null;
        $comparePrice = filled($attributes['compare_price'] ?? null) ? $attributes['compare_price'] : null;
        $isVariantProduct = ($attributes['product_type'] ?? 'simple') === 'variant';

        return [
            'category_id' => $category?->id,
            'brand_id' => $attributes['brand_id'] ?? null,
            'brand_name' => $attributes['brand_name'] ?? null,
            'sku' => $attributes['sku'] ?? null,
            'barcode' => $attributes['barcode'] ?? null,
            'model' => $attributes['model'] ?? null,
            'title' => $title,
            'slug' => $listing?->title === $title ? $listing?->slug : $this->uniqueSlug($title, $listing?->id),
            'short_description' => $attributes['short_description'] ?? null,
            'description' => $attributes['description'] ?? null,
            'condition' => $attributes['condition'] ?? null,
            'listing_type' => 'buy_now',
            'product_type' => $attributes['product_type'] ?? 'simple',
            'location' => $attributes['location'] ?? null,
            'warranty' => $attributes['warranty'] ?? null,
            'stock_quantity' => $isVariantProduct ? 0 : (int) ($attributes['stock_quantity'] ?? 0),
            'low_stock_threshold' => (int) ($attributes['low_stock_threshold'] ?? 0),
            'allow_backorders' => (bool) ($attributes['allow_backorders'] ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'is_featured' => (bool) ($attributes['is_featured'] ?? false),
            'is_best_seller' => (bool) ($attributes['is_best_seller'] ?? false),
            'is_new_arrival' => (bool) ($attributes['is_new_arrival'] ?? false),
            'price' => $isVariantProduct ? null : ($comparePrice ?? $sellingPrice),
            'sale_price' => $isVariantProduct || $comparePrice === null ? null : $sellingPrice,
            'commission_percentage' => $category?->commission_percentage,
            'meta_title' => $attributes['meta_title'] ?? null,
            'meta_description' => $attributes['meta_description'] ?? null,
        ];
    }

    private function synchronizeVariantSummary(Listing $listing): void
    {
        if ($listing->product_type !== 'variant') {
            return;
        }

        $activeVariants = $listing->variants()->where('is_active', true);
        $lowestPricedVariant = (clone $activeVariants)
            ->whereNotNull('selling_price')
            ->orderBy('selling_price')
            ->first();
        $sellingPrice = $lowestPricedVariant?->selling_price;
        $marketPrice = $lowestPricedVariant?->market_price;

        $listing->forceFill([
            'stock_quantity' => (clone $activeVariants)->sum('stock_quantity'),
            'price' => $marketPrice ?? $sellingPrice,
            'sale_price' => $marketPrice === null ? null : $sellingPrice,
        ]);
        $this->listings->save($listing);
    }

    private function ensureReadyForReview(Listing $listing): void
    {
        $validator = Validator::make([
            ...$listing->only([
                'category_id',
                'sku',
                'title',
                'description',
                'condition',
                'location',
                'price',
                'product_type',
            ]),
            'brand' => $listing->brand_id ?? $listing->brand_name,
            'media_count' => $listing->media()->count(),
            'variant_count' => $listing->variants()->count(),
            'variants_with_skus' => $listing->variants()->whereNotNull('sku')->count(),
        ], [
            'category_id' => ['required', 'integer'],
            'sku' => ['required', 'string'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'condition' => ['required', 'string'],
            'location' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:1'],
            'brand' => ['required'],
            'media_count' => ['integer', 'min:1'],
            'variant_count' => ['exclude_unless:product_type,variant', 'required', 'integer', 'min:1'],
            'variants_with_skus' => ['exclude_unless:product_type,variant', 'same:variant_count'],
        ], [
            'media_count.min' => 'Add at least one product image before submitting for review.',
            'variant_count.min' => 'Generate at least one complete variant before submitting for review.',
            'variants_with_skus.same' => 'Every variant needs a SKU before submitting for review.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function uniqueSlug(?string $title, ?int $exceptListingId = null): ?string
    {
        if ($title === null) {
            return null;
        }

        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        $counter = 2;

        while (Listing::query()->where('slug', $slug)->when($exceptListingId, fn ($query, int $listingId) => $query->whereKeyNot($listingId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
