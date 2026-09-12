<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\WholesalePriceTierRepository;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WholesalePriceTier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class ListingService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CatalogRepository $catalog,
        private readonly AuditLogService $auditLogs,
        private readonly ListingImageService $images,
        private readonly ListingVariantService $variants,
        private readonly WholesalePriceTierRepository $wholesalePriceTiers,
        private readonly ListingSeoMetadataService $seoMetadata,
        private readonly AuctionService $auctions,
        private readonly SeoRedirectService $redirects,
    ) {}

    /**
     * @param  array{q?: string, status?: string, sort?: string}  $filters
     * @return array{sellerStatus: string, listings: LengthAwarePaginator<int, Listing>, filters: array{q: string, status: string, sort: string}}
     */
    public function sellerIndex(User $seller, array $filters = []): array
    {
        $profile = $this->sellerProfileFor($seller);

        return [
            'sellerStatus' => $profile->status,
            'listings' => $this->listings->paginateForSeller($profile, $filters, 'retail'),
            'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? 'all', 'sort' => $filters['sort'] ?? 'newest'],
        ];
    }

    /**
     * @param  array{q?: string, status?: string, sort?: string}  $filters
     * @return array{sellerStatus: string, listings: LengthAwarePaginator<int, Listing>, filters: array{q: string, status: string, sort: string}}
     */
    public function sellerWholesaleIndex(User $seller, array $filters = []): array
    {
        $profile = $this->sellerProfileFor($seller);

        return [
            'sellerStatus' => $profile->status,
            'listings' => $this->listings->paginateForSeller($profile, $filters, 'wholesale'),
            'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? 'all', 'sort' => $filters['sort'] ?? 'newest'],
        ];
    }

    /** @return array{sellerStatus: string, brands: mixed, defaultChannel: 'retail'|'wholesale', auctionFlags: array{enabled: bool, types: array<string, bool>}, auctionDefaults: array{durationDays: int, extensionMinutes: int, startsAt: string, endsAt: string}} */
    public function sellerCreateData(User $seller, string $defaultChannel = 'retail'): array
    {
        $auctionData = $this->auctions->sellerCreateData($seller);

        return [
            'sellerStatus' => $this->sellerProfileFor($seller)->status,
            'brands' => $this->catalog->listingBrands(),
            'defaultChannel' => $defaultChannel === 'wholesale' ? 'wholesale' : 'retail',
            'auctionFlags' => $auctionData['flags'],
            'auctionDefaults' => $auctionData['defaults'],
        ];
    }

    public function sellerProduct(User $seller, int $listingId): Listing
    {
        $listing = $this->listings->findDetailedForSellerOrFail($this->sellerProfileFor($seller), $listingId);
        $listing->makeVisible(['cost_price', 'supplier_name', 'internal_notes']);
        $listing->variants->each->makeVisible('cost_price');

        return $listing;
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
            $this->synchronizeListingWholesaleTiers($listing, $attributes);
            $this->variants->synchronize($listing, $attributes);
            $this->synchronizeVariantSummary($listing);
            $this->storeImages($listing, $attributes['images'] ?? [], $attributes['image_crops'] ?? []);
            $this->synchronizeAuctionDraft($seller, $listing, $attributes);

            $this->auditLogs->record($seller, 'listing.draft_created', $listing, after: $listing->getAttributes());

            return $submitForReview ? $this->submitListing($seller, $listing) : $listing;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraft(User $seller, Listing $listing, array $attributes): Listing
    {
        $oldSlug = $listing->slug;
        $profile = $this->sellerProfileFor($seller);
        $submitForReview = (bool) ($attributes['submit_for_review'] ?? false);

        if ($submitForReview) {
            $this->ensureCanSubmit($profile);
        }

        return DB::transaction(function () use ($seller, $profile, $listing, $attributes, $submitForReview, $oldSlug): Listing {
            $listing = $this->listings->findForSellerOrFail($profile, $listing->id, lockForUpdate: true);

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
            $this->images->remove($listing, array_map('intval', $attributes['removed_media_ids'] ?? []));
            $this->synchronizeListingWholesaleTiers($listing, $attributes);
            $this->variants->synchronize($listing, $attributes);
            $this->synchronizeVariantSummary($listing);
            $this->synchronizeAuctionDraft($seller, $listing, $attributes);

            if ($attributes['images'] ?? []) {
                $this->storeImages($listing, $attributes['images'], $attributes['image_crops']);
            }

            $this->auditLogs->record($seller, 'listing.draft_updated', $listing, $before, $listing->getAttributes());

            $listing = $submitForReview ? $this->submitListing($seller, $listing) : $listing;
            $this->redirects->recordSlugChange('listings', $oldSlug, $listing->slug);

            return $listing;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateForModeration(User $actor, Listing $listing, array $attributes): Listing
    {
        $oldSlug = $listing->slug;

        return DB::transaction(function () use ($actor, $listing, $attributes, $oldSlug): Listing {
            $listing = $this->listings->findForAdminOrFail($listing->id, lockForUpdate: true);
            $before = $listing->getAttributes();

            $listing->forceFill([
                ...$this->productAttributes($attributes, $listing),
                'is_featured' => $listing->is_featured,
                'is_best_offer' => false,
                'is_best_seller' => $listing->is_best_seller,
                'is_new_arrival' => $listing->is_new_arrival,
            ]);
            $this->listings->save($listing);
            $this->images->remove($listing, array_map('intval', $attributes['removed_media_ids'] ?? []));
            $this->synchronizeListingWholesaleTiers($listing, $attributes);
            $this->variants->synchronize($listing, $attributes);
            $this->synchronizeVariantSummary($listing);

            if ($attributes['images'] ?? []) {
                $this->storeImages($listing, $attributes['images'], $attributes['image_crops']);
            }

            $this->auditLogs->record($actor, 'listing.details_updated_by_admin', $listing, $before, $listing->getAttributes());
            $this->redirects->recordSlugChange('listings', $oldSlug, $listing->slug);

            return $listing;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, array{x: int, y: int, width: int, height: int}>  $crops
     */
    public function updateDraftContent(User $seller, int $listingId, array $attributes, array $images = [], array $crops = []): Listing
    {
        $profile = $this->sellerProfileFor($seller);

        return DB::transaction(function () use ($seller, $profile, $listingId, $attributes, $images, $crops): Listing {
            $listing = $this->listings->findForSellerOrFail($profile, $listingId, lockForUpdate: true);
            $oldSlug = $listing->slug;

            if (! in_array($listing->status, ['draft', 'changes_requested', 'rejected'], true)) {
                throw new AuthorizationException('Only drafts and returned listings can be edited.');
            }

            if ($this->listings->mediaCount($listing) + count($images) > 5) {
                throw ValidationException::withMessages([
                    'images' => 'A product may have no more than five gallery images.',
                ]);
            }

            $before = $listing->getAttributes();
            $changes = Arr::only($attributes, ['title', 'short_description', 'description', 'warranty']);

            if (array_key_exists('title', $changes) && $changes['title'] !== $listing->title) {
                $changes['slug'] = $this->uniqueSlug($changes['title'], $listing->id);
            }

            if (array_key_exists('specifications_text', $attributes)) {
                $changes['specifications'] = $this->specificationAttributes($attributes['specifications_text']);
            }

            $listing->fill($changes);
            $titleChanged = $listing->isDirty('title');
            $descriptionChanged = $listing->isDirty(['title', 'short_description', 'description']);
            $listing->fill($this->seoMetadata->generate(
                $listing->title,
                $listing->short_description,
                $listing->description,
                array_key_exists('meta_title', $attributes)
                    ? $attributes['meta_title']
                    : ($titleChanged ? null : $listing->meta_title),
                array_key_exists('meta_description', $attributes)
                    ? $attributes['meta_description']
                    : ($descriptionChanged ? null : $listing->meta_description),
            ));
            $this->listings->save($listing);
            $this->storeImages($listing, $images, $crops);
            $this->auditLogs->record($seller, 'listing.draft_updated', $listing, $before, [
                ...$listing->getAttributes(),
                'media_count' => $this->listings->mediaCount($listing),
            ]);
            $this->redirects->recordSlugChange('listings', $oldSlug, $listing->slug);

            return $listing;
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
        $isWholesaleEnabled = (bool) ($attributes['is_wholesale_enabled'] ?? false);

        return [
            'category_id' => $category?->id,
            'brand_id' => $attributes['brand_id'] ?? null,
            'brand_name' => $attributes['brand_name'] ?? null,
            'sku' => $attributes['sku'] ?? null,
            'barcode' => $attributes['barcode'] ?? null,
            'gtin' => $isVariantProduct ? null : ($attributes['gtin'] ?? null),
            'mpn' => $isVariantProduct ? null : ($attributes['mpn'] ?? null),
            'model' => $attributes['model'] ?? null,
            'title' => $title,
            'slug' => $listing?->title === $title ? $listing?->slug : $this->uniqueSlug($title, $listing?->id),
            'short_description' => $attributes['short_description'] ?? null,
            'description' => $attributes['description'] ?? null,
            'specifications' => $this->specificationAttributes($attributes['specifications_text'] ?? null),
            'condition' => $attributes['condition'] ?? null,
            'listing_type' => 'buy_now',
            'is_retail_enabled' => (bool) ($attributes['is_retail_enabled'] ?? true),
            'is_wholesale_enabled' => $isWholesaleEnabled,
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
            'wholesale_price' => $isWholesaleEnabled ? $listing?->wholesale_price : null,
            'wholesale_min_quantity' => $isWholesaleEnabled ? $listing?->wholesale_min_quantity : null,
            'cost_price' => $isVariantProduct ? null : (array_key_exists('cost_price', $attributes) ? $attributes['cost_price'] : ($listing?->product_type === 'simple' ? $listing->cost_price : null)),
            'supplier_name' => array_key_exists('supplier_name', $attributes) ? $attributes['supplier_name'] : $listing?->supplier_name,
            'internal_notes' => array_key_exists('internal_notes', $attributes) ? $attributes['internal_notes'] : $listing?->internal_notes,
            'commission_percentage' => $category?->commission_percentage,
            'meta_title' => $attributes['meta_title'] ?? null,
            'meta_description' => $attributes['meta_description'] ?? null,
        ];
    }

    private function synchronizeVariantSummary(Listing $listing): void
    {
        $lowestWholesaleTier = $listing->is_wholesale_enabled
            ? $this->wholesalePriceTiers->lowestForListing($listing)
            : null;

        if ($listing->product_type !== 'variant') {
            $listing->forceFill([
                'wholesale_price' => $lowestWholesaleTier?->unit_price,
                'wholesale_min_quantity' => $lowestWholesaleTier?->minimum_quantity,
            ]);
            $this->listings->save($listing);

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
            'wholesale_price' => $lowestWholesaleTier?->unit_price,
            'wholesale_min_quantity' => $lowestWholesaleTier?->minimum_quantity,
        ]);
        $this->listings->save($listing);
    }

    /** @param array<string, mixed> $attributes */
    private function synchronizeListingWholesaleTiers(Listing $listing, array $attributes): void
    {
        $tiers = $listing->product_type === 'simple' && $listing->is_wholesale_enabled
            ? $this->normalizedWholesaleTiers($attributes['wholesale_tiers'] ?? [])
            : [];

        $this->wholesalePriceTiers->replaceForListing($listing, $tiers);
    }

    /** @return list<array{minimum_quantity: int, unit_price: mixed}> */
    private function normalizedWholesaleTiers(mixed $tiers): array
    {
        return array_values(collect(is_array($tiers) ? $tiers : [])
            ->filter(fn (mixed $tier): bool => filled(Arr::get((array) $tier, 'minimum_quantity')) && filled(Arr::get((array) $tier, 'unit_price')))
            ->map(fn (mixed $tier): array => [
                'minimum_quantity' => (int) Arr::get((array) $tier, 'minimum_quantity'),
                'unit_price' => Arr::get((array) $tier, 'unit_price'),
            ])
            ->sortBy('minimum_quantity')
            ->values()
            ->all());
    }

    /** @return array<string, string>|null */
    private function specificationAttributes(mixed $specifications): ?array
    {
        if (! is_string($specifications) || trim($specifications) === '') {
            return null;
        }

        return ['Details' => trim($specifications)];
    }

    private function ensureReadyForReview(Listing $listing): void
    {
        $listing->loadMissing(['wholesalePriceTiers', 'activeAuction']);
        $activeVariants = $listing->variants()->where('is_active', true)->with('wholesalePriceTiers')->get();
        $validator = ValidatorFacade::make([
            ...$listing->only([
                'category_id',
                'sku',
                'title',
                'description',
                'condition',
                'price',
                'product_type',
                'is_retail_enabled',
                'is_wholesale_enabled',
            ]),
            'wholesale_tiers' => $listing->wholesalePriceTiers->map(fn (WholesalePriceTier $tier): array => [
                'minimum_quantity' => $tier->minimum_quantity,
                'unit_price' => $tier->unit_price,
            ])->all(),
            'brand' => $listing->brand_id ?? $listing->brand_name,
            'media_count' => $listing->media()->count(),
            'variant_count' => $listing->variants()->count(),
            'variants_with_skus' => $listing->variants()->whereNotNull('sku')->count(),
            'active_variants' => $activeVariants->map(fn (ListingVariant $variant): array => [
                'selling_price' => $variant->selling_price,
                'wholesale_tiers' => $variant->wholesalePriceTiers->map(fn (WholesalePriceTier $tier): array => [
                    'minimum_quantity' => $tier->minimum_quantity,
                    'unit_price' => $tier->unit_price,
                ])->all(),
            ])->all(),
        ], [
            'category_id' => ['required', 'integer'],
            'sku' => ['required', 'string'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'condition' => ['required', 'string'],
            'price' => ['nullable', 'numeric', 'min:1', 'required_if:is_retail_enabled,1'],
            'wholesale_tiers' => ['exclude_unless:product_type,simple', 'nullable', 'array', 'max:3', 'required_if:is_wholesale_enabled,1'],
            'wholesale_tiers.*.unit_price' => ['required', 'numeric', 'min:1'],
            'wholesale_tiers.*.minimum_quantity' => ['required', 'integer', 'between:2,100000'],
            'is_retail_enabled' => ['required', 'boolean'],
            'is_wholesale_enabled' => ['required', 'boolean'],
            'brand' => ['required'],
            'media_count' => ['integer', 'min:1'],
            'variant_count' => ['exclude_unless:product_type,variant', 'required', 'integer', 'min:1'],
            'variants_with_skus' => ['exclude_unless:product_type,variant', 'same:variant_count'],
            'active_variants.*.selling_price' => ['nullable', 'numeric', 'min:1', 'required_if:is_retail_enabled,1'],
            'active_variants.*.wholesale_tiers' => ['nullable', 'array', 'max:3', 'required_if:is_wholesale_enabled,1'],
            'active_variants.*.wholesale_tiers.*.unit_price' => ['required', 'numeric', 'min:1'],
            'active_variants.*.wholesale_tiers.*.minimum_quantity' => ['required', 'integer', 'between:2,100000'],
        ], [
            'media_count.min' => 'Add at least one product image before submitting for review.',
            'variant_count.min' => 'Generate at least one complete variant before submitting for review.',
            'variants_with_skus.same' => 'Every variant needs a SKU before submitting for review.',
        ]);

        $validator->after(function ($validator) use ($listing, $activeVariants): void {
            if (! $listing->is_retail_enabled && ! $listing->is_wholesale_enabled && $listing->activeAuction === null) {
                $validator->errors()->add('is_retail_enabled', 'Choose at least one sales channel.');
            }

            if ($listing->product_type === 'simple') {
                if ($listing->is_wholesale_enabled) {
                    $this->validateStoredWholesaleTiers(
                        $validator,
                        $listing->wholesalePriceTiers,
                        $listing->is_retail_enabled ? $listing->buyNowPrice() : null,
                        'wholesale_tiers',
                    );
                }

                return;
            }

            foreach ($activeVariants as $index => $variant) {
                if ($listing->is_wholesale_enabled) {
                    $this->validateStoredWholesaleTiers(
                        $validator,
                        $variant->wholesalePriceTiers,
                        $listing->is_retail_enabled ? $variant->selling_price : null,
                        "active_variants.{$index}.wholesale_tiers",
                    );
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function synchronizeAuctionDraft(User $seller, Listing $listing, array $attributes): void
    {
        $auction = $listing->activeAuction()->first();
        if (! (bool) ($attributes['auction_enabled'] ?? false)) {
            if ($auction !== null) {
                $this->auctions->deleteDraft($seller, $auction->id);
            }

            return;
        }

        $auctionAttributes = (array) ($attributes['auction'] ?? []);
        $variantSku = $auctionAttributes['variant_sku'] ?? null;
        if (! filled($auctionAttributes['listing_variant_id'] ?? null) && filled($variantSku)) {
            $auctionAttributes['listing_variant_id'] = $listing->variants()->where('sku', $variantSku)->value('id');
        }
        $auctionAttributes['listing_id'] = $listing->id;

        if ($auction === null) {
            $this->auctions->create($seller, $auctionAttributes);

            return;
        }

        $this->auctions->updateDraft($seller, $auction->id, $auctionAttributes);
    }

    /** @param Collection<int, WholesalePriceTier> $tiers */
    private function validateStoredWholesaleTiers(Validator $validator, Collection $tiers, mixed $retailPrice, string $field): void
    {
        $previousQuantity = null;
        $previousPrice = null;

        foreach ($tiers->sortBy('minimum_quantity')->values() as $index => $tier) {
            if ($previousQuantity !== null && $tier->minimum_quantity <= $previousQuantity) {
                $validator->errors()->add("{$field}.{$index}.minimum_quantity", 'Each quantity must be greater than the tier before it.');
            }

            if ($previousPrice !== null && (float) $tier->unit_price > $previousPrice) {
                $validator->errors()->add("{$field}.{$index}.unit_price", 'Higher quantity tiers cannot have a higher unit price.');
            }

            if (is_numeric($retailPrice) && (float) $tier->unit_price >= (float) $retailPrice) {
                $validator->errors()->add("{$field}.{$index}.unit_price", 'The wholesale price must be lower than the retail selling price.');
            }

            $previousQuantity = $tier->minimum_quantity;
            $previousPrice = (float) $tier->unit_price;
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

        while ($this->listings->slugExists($slug, $exceptListingId)) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
