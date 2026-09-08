<?php

namespace App\Services;

use App\AuctionStatus;
use App\AuctionType;
use App\Contracts\Repositories\AuctionRepository;
use App\Contracts\Repositories\MarketplaceSettingRepository;
use App\Models\Auction;
use App\Models\AuctionOffer;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionService
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly MarketplaceSettingsService $settings,
        private readonly MarketplaceSettingRepository $settingRepository,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @return LengthAwarePaginator<int, Auction> */
    public function sellerIndex(User $seller): LengthAwarePaginator
    {
        return $this->auctions->paginateForSeller($seller);
    }

    /** @return LengthAwarePaginator<int, Auction> */
    public function adminIndex(): LengthAwarePaginator
    {
        return $this->auctions->paginateForAdmin();
    }

    /** @return array{listings: mixed, flags: array{enabled: bool, types: array<string, bool>}, defaults: array{durationDays: int, extensionMinutes: int, startsAt: string, endsAt: string}} */
    public function sellerCreateData(User $seller): array
    {
        $durationDays = $this->settings->integer('auction.default_duration_days', 7);

        return [
            'listings' => $this->auctions->eligibleListingsForSeller($seller),
            'flags' => $this->settings->auctionFlags(),
            'defaults' => [
                'durationDays' => $durationDays,
                'extensionMinutes' => $this->settings->integer('auction.anti_sniping_extension_minutes', 5),
                'startsAt' => now()->addHour()->toIso8601String(),
                'endsAt' => now()->addDays($durationDays)->toIso8601String(),
            ],
        ];
    }

    public function sellerAuction(User $seller, int $auctionId): Auction
    {
        return $this->auctions->findForSellerOrFail($seller, $auctionId);
    }

    public function adminAuction(int $auctionId): Auction
    {
        return $this->auctions->findForAdminOrFail($auctionId);
    }

    /** @return LengthAwarePaginator<int, AuctionOffer> */
    public function buyerOffers(User $buyer): LengthAwarePaginator
    {
        return $this->auctions->paginateOffersForBuyer($buyer);
    }

    public function buyerOffer(User $buyer, int $offerId): AuctionOffer
    {
        return $this->auctions->offerForBuyerOrFail($buyer, $offerId);
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $seller, array $attributes): Auction
    {
        return DB::transaction(function () use ($seller, $attributes): Auction {
            $listing = $this->auctions->listingForSellerOrFail($seller, (int) $attributes['listing_id'], true);
            $type = AuctionType::from((string) $attributes['type']);
            $this->ensureCreationEnabled($type);
            $this->ensureListingCanHaveAuction($listing);
            $this->ensureVariantAndQuantityAreValid($listing, $attributes);

            $startsAt = Carbon::parse((string) $attributes['starts_at']);
            $auction = $this->auctions->create([
                'listing_id' => $listing->id,
                'listing_variant_id' => $attributes['listing_variant_id'] ?? null,
                'quantity' => (int) $attributes['quantity'],
                'status' => AuctionStatus::Draft,
                'type' => $type,
                'starting_price' => $attributes['starting_price'],
                'minimum_increment' => $attributes['minimum_increment'],
                'extension_window_minutes' => $type === AuctionType::TimeExtended
                    ? (int) ($attributes['extension_window_minutes'] ?? $this->settings->integer('auction.anti_sniping_extension_minutes', 5))
                    : 0,
                'starts_at' => $startsAt,
                'ends_at' => Carbon::parse((string) $attributes['ends_at']),
            ]);

            if ($listing->status === 'approved') {
                $auction = $this->schedule($auction);
            }

            $this->auditLogs->record($seller, 'auction.created', $auction, after: $auction->getAttributes());

            return $auction;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraft(User $seller, int $auctionId, array $attributes): Auction
    {
        return DB::transaction(function () use ($seller, $auctionId, $attributes): Auction {
            $auction = $this->auctions->findForSellerOrFail($seller, $auctionId, true);
            if ($auction->status !== AuctionStatus::Draft) {
                throw new AuthorizationException('Only draft auctions can be edited.');
            }

            $type = AuctionType::from((string) $attributes['type']);
            $this->ensureCreationEnabled($type);
            $this->ensureVariantAndQuantityAreValid($auction->listing, $attributes);
            $before = $auction->getAttributes();
            $auction = $this->auctions->save($auction, [
                'listing_variant_id' => $attributes['listing_variant_id'] ?? null,
                'quantity' => (int) $attributes['quantity'],
                'type' => $type,
                'starting_price' => $attributes['starting_price'],
                'minimum_increment' => $attributes['minimum_increment'],
                'extension_window_minutes' => $type === AuctionType::TimeExtended
                    ? (int) ($attributes['extension_window_minutes'] ?? 5)
                    : 0,
                'starts_at' => Carbon::parse((string) $attributes['starts_at']),
                'ends_at' => Carbon::parse((string) $attributes['ends_at']),
            ]);

            if ($auction->listing->status === 'approved') {
                $auction = $this->schedule($auction);
            }

            $this->auditLogs->record($seller, 'auction.draft_updated', $auction, $before, $auction->getAttributes());

            return $auction;
        }, attempts: 3);
    }

    public function deleteDraft(User $seller, int $auctionId): void
    {
        DB::transaction(function () use ($seller, $auctionId): void {
            $auction = $this->auctions->findForSellerOrFail($seller, $auctionId, true);
            if ($auction->status !== AuctionStatus::Draft) {
                throw new AuthorizationException('Only draft auctions can be removed.');
            }

            $this->auditLogs->record($seller, 'auction.draft_removed', $auction, before: $auction->getAttributes());
            $this->auctions->delete($auction);
        });
    }

    public function scheduleApprovedListing(int $listingId): void
    {
        DB::transaction(function () use ($listingId): void {
            $listing = $this->auctions->listingForUpdate($listingId);
            $auction = $listing->activeAuction;
            if ($listing->status !== 'approved' || $auction === null || $auction->status !== AuctionStatus::Draft) {
                return;
            }

            if ($auction->starts_at->lessThanOrEqualTo(now())) {
                return;
            }

            $this->schedule($auction);
        }, attempts: 3);
    }

    public function cancel(User $admin, int $auctionId, string $reason): Auction
    {
        return DB::transaction(function () use ($admin, $auctionId, $reason): Auction {
            $auction = $this->auctions->findForAdminOrFail($auctionId, true);
            if (! in_array($auction->status, [AuctionStatus::Scheduled, AuctionStatus::Live, AuctionStatus::OfferPending], true)) {
                throw ValidationException::withMessages(['auction' => 'Only an unpaid active auction can be cancelled.']);
            }

            if ($auction->offers->contains(fn ($offer): bool => $offer->status->value === 'paid')) {
                throw ValidationException::withMessages(['auction' => 'Paid auctions must use the normal refund workflow.']);
            }

            $before = $auction->getAttributes();
            $this->auctions->releaseInventory($auction);
            $auction = $this->auctions->save($auction, [
                'status' => AuctionStatus::Cancelled,
                'cancellation_reason' => $reason,
                'inventory_released_at' => now(),
                'closed_at' => now(),
            ]);
            foreach ($auction->offers as $offer) {
                if (! in_array($offer->status->value, ['paid', 'expired'], true)) {
                    $this->auctions->saveOffer($offer, ['status' => 'cancelled']);
                }
            }
            $this->auditLogs->record($admin, 'auction.cancelled', $auction, $before, $auction->getAttributes(), $reason);

            return $auction;
        }, attempts: 3);
    }

    /** @param array<string, bool> $flags */
    public function updateFlags(User $admin, array $flags): void
    {
        DB::transaction(function () use ($admin, $flags): void {
            foreach ($flags as $key => $value) {
                $setting = $this->settingRepository->update($key, $value, $admin->id);
                $this->auditLogs->record($admin, 'auction.setting_updated', $setting, after: ['value' => $value]);
            }
        });
    }

    private function schedule(Auction $auction): Auction
    {
        if ($auction->starts_at->lessThanOrEqualTo(now()) || $auction->ends_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future auction start time.']);
        }

        $this->auctions->reserveInventory($auction);

        return $this->auctions->save($auction, [
            'status' => AuctionStatus::Scheduled,
            'inventory_reserved_at' => now(),
            'inventory_released_at' => null,
        ]);
    }

    private function ensureCreationEnabled(AuctionType $type): void
    {
        if (! $this->settings->auctionTypeEnabled($type)) {
            throw ValidationException::withMessages(['type' => 'This auction type is currently disabled.']);
        }
    }

    private function ensureListingCanHaveAuction(Listing $listing): void
    {
        if ($this->auctions->hasNonTerminalAuction($listing->id)) {
            throw ValidationException::withMessages(['listing_id' => 'This product already has an active or draft auction.']);
        }

        if (! in_array($listing->status, ['draft', 'changes_requested', 'rejected', 'pending_review', 'approved'], true)) {
            throw ValidationException::withMessages(['listing_id' => 'This product is not eligible for an auction.']);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function ensureVariantAndQuantityAreValid(Listing $listing, array $attributes): void
    {
        $variantId = filled($attributes['listing_variant_id'] ?? null) ? (int) $attributes['listing_variant_id'] : null;
        if ($listing->product_type === 'variant') {
            $variant = $listing->variants->firstWhere('id', $variantId);
            if ($variant === null || ! $variant->is_active) {
                throw ValidationException::withMessages(['listing_variant_id' => 'Choose an active product variant.']);
            }
        } elseif ($variantId !== null) {
            throw ValidationException::withMessages(['listing_variant_id' => 'A variant cannot be selected for this product.']);
        }

        if ((int) $attributes['quantity'] < 1) {
            throw ValidationException::withMessages(['quantity' => 'The auction quantity must be at least one.']);
        }
    }
}
