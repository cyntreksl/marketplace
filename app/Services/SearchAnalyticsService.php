<?php

namespace App\Services;

use App\Contracts\Repositories\SearchEventRepository;
use App\Models\SearchEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchAnalyticsService
{
    private const DEDUPLICATION_MINUTES = 30;

    private const SESSION_VISITOR_KEY = 'search_analytics.visitor_id';

    private const SESSION_RECENT_KEY = 'search_analytics.recent_terms';

    public function __construct(private readonly SearchEventRepository $events) {}

    /** @param array<string, mixed> $filters */
    public function track(Request $request, array $filters, string $context, int $resultCount): void
    {
        $term = Str::squish((string) ($filters['search'] ?? ''));

        if ($term === '' || $request->integer('page', 1) > 1) {
            return;
        }

        $normalizedTerm = Str::lower($term);
        $termKey = hash('sha256', $normalizedTerm);
        $deduplicationCutoff = now()->subMinutes(self::DEDUPLICATION_MINUTES)->timestamp;
        $storedRecentTerms = $request->session()->get(self::SESSION_RECENT_KEY, []);
        $recentTerms = collect(is_array($storedRecentTerms) ? $storedRecentTerms : [])
            ->filter(fn (mixed $timestamp): bool => is_numeric($timestamp) && (int) $timestamp > $deduplicationCutoff)
            ->all();
        $lastTrackedTimestamp = (int) ($recentTerms[$termKey] ?? 0);

        if ($lastTrackedTimestamp > $deduplicationCutoff) {
            return;
        }

        $recentTerms[$termKey] = now()->timestamp;
        $request->session()->put(self::SESSION_RECENT_KEY, $recentTerms);

        $visitorId = $request->session()->get(self::SESSION_VISITOR_KEY);

        if (! is_string($visitorId)) {
            $visitorId = (string) Str::uuid();
            $request->session()->put(self::SESSION_VISITOR_KEY, $visitorId);
        }

        $activeFilters = collect($filters)
            ->except(['search', 'sort'])
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        $attributes = [
            'user_id' => $request->user()?->id,
            'visitor_id' => $visitorId,
            'term' => $term,
            'normalized_term' => $normalizedTerm,
            'result_count' => $resultCount,
            'context' => $context,
            'filters' => $activeFilters === [] ? null : $activeFilters,
            'searched_at' => now(),
        ];

        defer(fn (): SearchEvent => $this->events->create($attributes));
    }

    /** @return array<string, mixed> */
    public function report(int $days): array
    {
        $since = now()->subDays($days);
        $summary = $this->events->summarySince($since);
        $total = $summary['total'];

        $recentSearches = $this->events->recentSince($since);

        return [
            'periodDays' => $days,
            'metrics' => [
                'totalSearches' => $total,
                'uniqueTerms' => $summary['unique_terms'],
                'zeroResultRate' => $total === 0 ? 0 : round(($summary['zero_result_count'] / $total) * 100, 1),
            ],
            'topTerms' => $this->termRows($this->events->topTermsSince($since)),
            'zeroResultTerms' => $this->termRows($this->events->topTermsSince($since, true)),
            'recentSearches' => [
                'data' => $recentSearches->getCollection()->map(fn (SearchEvent $event): array => [
                    'id' => $event->id,
                    'term' => $event->term,
                    'resultCount' => $event->result_count,
                    'context' => $event->context,
                    'user' => $event->user?->only(['id', 'name', 'email']),
                    'searchedAt' => $event->searched_at->toIso8601String(),
                ])->all(),
                'current_page' => $recentSearches->currentPage(),
                'last_page' => $recentSearches->lastPage(),
                'links' => $recentSearches->linkCollection()->all(),
                'total' => $recentSearches->total(),
            ],
        ];
    }

    /**
     * @param  Collection<int, SearchEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function termRows(Collection $events): array
    {
        return $events->map(fn (SearchEvent $event): array => [
            'term' => $event->term,
            'normalizedTerm' => $event->normalized_term,
            'searchCount' => (int) $event->getAttribute('search_count'),
            'averageResultCount' => round((float) $event->getAttribute('average_result_count'), 1),
            'latestResultCount' => (int) $event->getAttribute('latest_result_count'),
            'lastSearchedAt' => Carbon::parse($event->getAttribute('last_searched_at'))->toIso8601String(),
        ])->all();
    }
}
