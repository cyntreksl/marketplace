<?php

namespace App\Contracts\Repositories;

use App\Models\SearchEvent;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SearchEventRepository
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): SearchEvent;

    /** @return array{total: int, unique_terms: int, zero_result_count: int} */
    public function summarySince(CarbonInterface $since): array;

    /** @return Collection<int, SearchEvent> */
    public function topTermsSince(CarbonInterface $since, bool $zeroResultsOnly = false, int $limit = 10): Collection;

    /** @return LengthAwarePaginator<int, SearchEvent> */
    public function recentSince(CarbonInterface $since, int $perPage = 25): LengthAwarePaginator;
}
