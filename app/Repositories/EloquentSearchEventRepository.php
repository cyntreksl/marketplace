<?php

namespace App\Repositories;

use App\Contracts\Repositories\SearchEventRepository;
use App\Models\SearchEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentSearchEventRepository implements SearchEventRepository
{
    public function create(array $attributes): SearchEvent
    {
        return SearchEvent::query()->create($attributes);
    }

    public function summarySince(CarbonInterface $since): array
    {
        $query = SearchEvent::query()->where('searched_at', '>=', $since);

        return [
            'total' => (clone $query)->count(),
            'unique_terms' => (clone $query)->distinct()->count('normalized_term'),
            'zero_result_count' => (clone $query)->where('result_count', 0)->count(),
        ];
    }

    public function topTermsSince(CarbonInterface $since, bool $zeroResultsOnly = false, int $limit = 10): Collection
    {
        $terms = SearchEvent::query()
            ->select('normalized_term')
            ->selectRaw('COUNT(*) as search_count')
            ->selectRaw('AVG(result_count) as average_result_count')
            ->selectRaw('MAX(searched_at) as last_searched_at')
            ->where('searched_at', '>=', $since)
            ->when($zeroResultsOnly, fn (Builder $query): Builder => $query->where('result_count', 0))
            ->groupBy('normalized_term')
            ->orderByDesc('search_count')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->get();

        $latestEvents = SearchEvent::query()
            ->where('searched_at', '>=', $since)
            ->whereIn('normalized_term', $terms->pluck('normalized_term'))
            ->latest('searched_at')
            ->latest('id')
            ->get()
            ->unique('normalized_term')
            ->keyBy('normalized_term');

        return $terms->each(function (SearchEvent $term) use ($latestEvents): void {
            $latest = $latestEvents->get($term->normalized_term);
            $term->setAttribute('term', $latest->term);
            $term->setAttribute('latest_result_count', $latest->result_count);
        });
    }

    public function recentSince(CarbonInterface $since, int $perPage = 25): LengthAwarePaginator
    {
        return SearchEvent::query()
            ->with('user:id,name,email')
            ->where('searched_at', '>=', $since)
            ->latest('searched_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
