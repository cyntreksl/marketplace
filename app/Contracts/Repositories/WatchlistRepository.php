<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Support\Collection;

interface WatchlistRepository
{
    /** @return Collection<int, Listing> */
    public function listingsFor(User $user): Collection;

    public function contains(User $user, Listing $listing): bool;

    public function add(User $user, Listing $listing): Watchlist;

    public function remove(User $user, Listing $listing): void;

    public function countFor(User $user): int;
}
