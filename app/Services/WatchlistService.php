<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\WatchlistRepository;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;

class WatchlistService
{
    public function __construct(
        private readonly WatchlistRepository $watchlists,
        private readonly ListingRepository $listings,
    ) {}

    /** @return Collection<int, Listing> */
    public function listingsFor(User $user): Collection
    {
        return $this->watchlists->listingsFor($user);
    }

    public function add(User $user, string $listingSlug): void
    {
        $this->watchlists->add($user, $this->listings->findPublicBySlug($listingSlug));
    }

    public function remove(User $user, Listing $listing): void
    {
        $this->watchlists->remove($user, $listing);
    }
}
