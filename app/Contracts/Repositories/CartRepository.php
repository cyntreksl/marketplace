<?php

namespace App\Contracts\Repositories;

use App\Models\Cart;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;

interface CartRepository
{
    public function forBuyer(User $buyer): Cart;

    /** @param list<int> $ids
     * @return Collection<int, Listing>
     */
    public function listings(array $ids): Collection;

    /** @param list<array{listing_id: int, listing_variant_id: int|null, selection_key: string, quantity: int}> $items */
    public function merge(User $buyer, array $items, string $token): void;

    public function lock(User $buyer): void;

    public function setQuantity(User $buyer, int $listingId, ?int $variantId, string $selectionKey, int $quantity, bool $increment = false): void;

    public function remove(User $buyer, int $itemId): void;
}
