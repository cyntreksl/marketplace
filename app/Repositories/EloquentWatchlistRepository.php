<?php

namespace App\Repositories;

use App\Contracts\Repositories\WatchlistRepository;
use App\Models\Listing;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Support\Collection;

class EloquentWatchlistRepository implements WatchlistRepository
{
    public function listingsFor(User $user): Collection
    {
        return Listing::query()
            ->publiclyVisible()
            ->whereHas('watchlistEntries', fn ($query) => $query->where('buyer_id', $user->id))
            ->with($this->cardRelations())
            ->withCount('reviews')
            ->withAvg('reviews as rating_average', 'rating')
            ->latest('watchlists.created_at')
            ->join('watchlists', 'watchlists.listing_id', '=', 'listings.id')
            ->whereNull('watchlists.deleted_at')
            ->select('listings.*')
            ->get();
    }

    public function contains(User $user, Listing $listing): bool
    {
        return Watchlist::query()->where('buyer_id', $user->id)->where('listing_id', $listing->id)->exists();
    }

    public function add(User $user, Listing $listing): Watchlist
    {
        $entry = Watchlist::withTrashed()->firstOrCreate(['buyer_id' => $user->id, 'listing_id' => $listing->id]);

        if ($entry->trashed()) {
            $entry->restore();
        }

        return $entry;
    }

    public function remove(User $user, Listing $listing): void
    {
        Watchlist::query()->where('buyer_id', $user->id)->where('listing_id', $listing->id)->delete();
    }

    public function countFor(User $user): int
    {
        return Watchlist::query()->where('buyer_id', $user->id)->count();
    }

    /** @return array<int, string> */
    private function cardRelations(): array
    {
        return ['category:id,name,slug', 'brand:id,name,slug', 'sellerProfile:id,store_name,slug', 'media', 'auction:id,listing_id,status,current_price,minimum_increment,ends_at'];
    }
}
