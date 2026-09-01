<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProductQuestionRepository;
use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentProductQuestionRepository implements ProductQuestionRepository
{
    public function answeredFor(Listing $listing, int $limit = 20): Collection
    {
        return ProductQuestion::query()
            ->whereBelongsTo($listing)
            ->whereNotNull('answered_at')
            ->with(['asker:id,name', 'answerer:id,name'])
            ->latest('answered_at')
            ->limit($limit)
            ->get();
    }

    public function pendingForViewer(Listing $listing, ?User $viewer): Collection
    {
        if ($viewer === null) {
            return collect();
        }

        return ProductQuestion::query()
            ->whereBelongsTo($listing)
            ->where('asked_by', $viewer->id)
            ->whereNull('answered_at')
            ->with(['asker:id,name', 'answerer:id,name'])
            ->latest()
            ->get();
    }

    public function create(Listing $listing, User $asker, string $question): ProductQuestion
    {
        return ProductQuestion::query()->create([
            'listing_id' => $listing->id,
            'asked_by' => $asker->id,
            'question' => $question,
        ]);
    }

    public function save(ProductQuestion $question): ProductQuestion
    {
        $question->save();

        return $question;
    }

    public function queueFor(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $isOperationsUser = $user->roles()->whereIn('name', [Role::Admin, Role::SuperAdmin])->exists();
        $sellerProfileId = $user->sellerProfile()->value('id');

        return ProductQuestion::query()
            ->with(['listing:id,title,slug,seller_profile_id', 'asker:id,name', 'answerer:id,name'])
            ->when(! $isOperationsUser, fn ($query) => $query->whereHas('listing', fn ($listingQuery) => $listingQuery->where('seller_profile_id', $sellerProfileId)))
            ->latest()
            ->paginate($perPage);
    }
}
