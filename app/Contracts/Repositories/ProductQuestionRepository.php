<?php

namespace App\Contracts\Repositories;

use App\Models\Listing;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductQuestionRepository
{
    /** @return Collection<int, ProductQuestion> */
    public function answeredFor(Listing $listing, int $limit = 20): Collection;

    /** @return Collection<int, ProductQuestion> */
    public function pendingForViewer(Listing $listing, ?User $viewer): Collection;

    public function create(Listing $listing, User $asker, string $question): ProductQuestion;

    public function save(ProductQuestion $question): ProductQuestion;

    /** @return LengthAwarePaginator<int, ProductQuestion> */
    public function queueFor(User $user, int $perPage = 20): LengthAwarePaginator;
}
