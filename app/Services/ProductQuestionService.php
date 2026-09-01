<?php

namespace App\Services;

use App\Contracts\Repositories\ProductQuestionRepository;
use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductQuestionService
{
    public function __construct(private readonly ProductQuestionRepository $questions) {}

    public function ask(User $asker, Listing $listing, string $question): ProductQuestion
    {
        abort_unless(Listing::query()->publiclyVisible()->whereKey($listing->id)->exists(), 404);

        return $this->questions->create($listing, $asker, $question);
    }

    /** @throws AuthorizationException */
    public function answer(User $answerer, ProductQuestion $question, string $answer): ProductQuestion
    {
        $isOperationsUser = $answerer->roles()->whereIn('name', [Role::Admin, Role::SuperAdmin])->exists();
        $ownsListing = $answerer->sellerProfile()->value('id') === $question->listing()->value('seller_profile_id');

        if (! $isOperationsUser && ! $ownsListing) {
            throw new AuthorizationException;
        }

        $question->forceFill([
            'answer' => $answer,
            'answered_by' => $answerer->id,
            'answered_at' => now(),
        ]);

        return $this->questions->save($question);
    }

    /** @return LengthAwarePaginator<int, ProductQuestion> */
    public function queueFor(User $user): LengthAwarePaginator
    {
        abort_unless($user->sellerProfile()->exists() || $user->roles()->whereIn('name', [Role::Admin, Role::SuperAdmin])->exists(), 403);

        return $this->questions->queueFor($user);
    }
}
