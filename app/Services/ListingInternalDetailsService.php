<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\ListingVariantRepository;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingInternalDetailsService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly ListingVariantRepository $variants,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function update(User $seller, int $listingId, array $attributes): void
    {
        DB::transaction(function () use ($seller, $listingId, $attributes): void {
            $profile = $seller->sellerProfile;
            abort_if($profile === null, 403);
            $listing = $this->listings->findForSellerOrFail($profile, $listingId, lockForUpdate: true);
            abort_if($listing->status === 'archived', 403);
            $variants = $this->variants->lockForListing($listing)->keyBy('id');
            $variantCosts = $attributes['variants'] ?? [];

            if (($listing->product_type !== 'variant' && $variantCosts !== [])
                || ($listing->product_type === 'variant' && filled($attributes['cost_price'] ?? null))) {
                throw ValidationException::withMessages(['cost_price' => 'The product type changed. Reload before saving costs.']);
            }

            foreach ($variantCosts as $index => $cost) {
                if (! $variants->has($cost['id'])) {
                    throw ValidationException::withMessages(["variants.{$index}.id" => 'This variant does not belong to this product. Reload and try again.']);
                }
            }

            $before = [
                ...$listing->only(['cost_price', 'supplier_name', 'internal_notes']),
                'variants' => $variants->map->only(['id', 'cost_price'])->values()->all(),
            ];
            $fields = $listing->product_type === 'simple'
                ? ['cost_price', 'supplier_name', 'internal_notes']
                : ['supplier_name', 'internal_notes'];
            $listing->fill(Arr::only($attributes, $fields));
            $this->listings->save($listing);

            foreach ($variantCosts as $cost) {
                $this->variants->updateCost($variants->get($cost['id']), $cost['cost_price']);
            }

            $this->auditLogs->record($seller, 'listing.internal_details_updated', $listing, $before, [
                ...$listing->only(['cost_price', 'supplier_name', 'internal_notes']),
                'variants' => $variants->map->only(['id', 'cost_price'])->values()->all(),
            ]);
        });
    }
}
