<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Models\Category;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class CategorySuggestionService
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    /**
     * @return Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string, score: float, reason: string}>
     */
    public function suggest(string $title, int $limit, ?int $currentParentId = null, ?string $topPath = null): Collection
    {
        $normalizedTitle = Str::limit(Str::squish($title), 160, '');
        $limit = max(1, min(8, $limit));
        $candidates = $this->candidateOptions($normalizedTitle, $currentParentId);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $suggestions = $this->openAiSuggestions($normalizedTitle, $candidates, $limit, $topPath);

        if ($suggestions->isNotEmpty()) {
            return $suggestions;
        }

        return $this->fallbackSuggestions($normalizedTitle, $candidates, $limit);
    }

    /**
     * @return Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}>
     */
    private function candidateOptions(string $title, ?int $currentParentId): Collection
    {
        $candidateLimit = max(20, (int) config('services.openai.category_suggestions.candidate_limit', 80));
        $candidates = collect();

        foreach ($this->candidateSearches($title) as $search) {
            $candidates = $candidates
                ->merge($this->catalog->lookupCategories($search, $currentParentId, true)->map(fn (Category $category): array => $this->optionFromCategory($category)))
                ->unique('id')
                ->values();

            if ($candidates->count() >= $candidateLimit) {
                return $candidates->take($candidateLimit);
            }
        }

        if ($candidates->count() < $candidateLimit) {
            $candidates = $candidates
                ->merge($this->catalog->lookupCategories(null, $currentParentId, true)->map(fn (Category $category): array => $this->optionFromCategory($category)))
                ->unique('id')
                ->values();
        }

        return $candidates->take($candidateLimit);
    }

    /** @return array<int, string|null> */
    private function candidateSearches(string $title): array
    {
        $terms = Str::of($title)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->explode(' ')
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => Str::length($term) >= 3)
            ->unique()
            ->take(5)
            ->values()
            ->all();

        return array_values(array_unique([$title, ...$terms]));
    }

    /**
     * @param  Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}>  $candidates
     * @return Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string, score: float, reason: string}>
     */
    private function openAiSuggestions(string $title, Collection $candidates, int $limit, ?string $topPath): Collection
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            return collect();
        }

        try {
            $payload = $this->openAiPayload($title, $candidates, $limit, $topPath);
            $request = Http::baseUrl((string) config('services.openai.base_url', 'https://api.openai.com/v1'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('services.openai.category_suggestions.timeout', 6)));

            $organization = config('services.openai.organization');
            if (is_string($organization) && $organization !== '') {
                $request = $request->withHeaders(['OpenAI-Organization' => $organization]);
            }

            $response = $request->post('/responses', $payload);

            if (! $response->successful()) {
                return collect();
            }

            return $this->rankedSuggestions($response->json(), $candidates, $limit);
        } catch (ConnectionException|JsonException) {
            return collect();
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @param  Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}>  $candidates
     * @return array<string, mixed>
     */
    private function openAiPayload(string $title, Collection $candidates, int $limit, ?string $topPath): array
    {
        return [
            'model' => (string) config('services.openai.category_suggestions.model', 'gpt-4o-mini'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => 'Rank marketplace product category candidates for a seller listing. Return JSON only. Use only candidate IDs from the provided list. Prefer specific leaf categories over broad categories.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'product_title' => $title,
                        'optional_top_path_context' => $topPath,
                        'result_limit' => $limit,
                        'candidates' => $candidates->map(fn (array $candidate): array => Arr::only($candidate, ['id', 'name', 'path']))->values()->all(),
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'category_suggestions',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['matches'],
                        'properties' => [
                            'matches' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['category_id', 'score', 'reason'],
                                    'properties' => [
                                        'category_id' => ['type' => 'integer'],
                                        'score' => ['type' => 'number'],
                                        'reason' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'max_output_tokens' => 500,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}>  $candidates
     * @return Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string, score: float, reason: string}>
     *
     * @throws JsonException
     */
    private function rankedSuggestions(array $response, Collection $candidates, int $limit): Collection
    {
        $content = $this->responseText($response);
        if ($content === null) {
            return collect();
        }

        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $candidateMap = $candidates->keyBy('id');

        return collect(Arr::get($decoded, 'matches', []))
            ->filter(fn (mixed $match): bool => is_array($match) && $candidateMap->has((int) Arr::get($match, 'category_id')))
            ->map(function (array $match) use ($candidateMap): array {
                $candidate = $candidateMap->get((int) $match['category_id']);

                return [
                    ...$candidate,
                    'score' => round(max(0, min(1, (float) $match['score'])), 2),
                    'reason' => Str::limit(Str::squish((string) $match['reason']), 140, ''),
                ];
            })
            ->unique('id')
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /** @param array<string, mixed> $response */
    private function responseText(array $response): ?string
    {
        if (is_string(Arr::get($response, 'output_text'))) {
            return Arr::get($response, 'output_text');
        }

        foreach (Arr::get($response, 'output', []) as $output) {
            foreach (Arr::get((array) $output, 'content', []) as $content) {
                $text = Arr::get((array) $content, 'text');

                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string}>  $candidates
     * @return Collection<int, array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string, score: float, reason: string}>
     */
    private function fallbackSuggestions(string $title, Collection $candidates, int $limit): Collection
    {
        $terms = collect($this->candidateSearches($title))
            ->filter()
            ->map(fn (string $term): string => Str::lower($term))
            ->values();

        return $candidates
            ->map(function (array $candidate) use ($terms): array {
                $path = Str::lower($candidate['path']);
                $name = Str::lower($candidate['name']);
                $score = $terms->reduce(function (float $score, string $term) use ($name, $path): float {
                    if (Str::contains($name, $term)) {
                        return $score + 0.28;
                    }

                    if (Str::contains($path, $term)) {
                        return $score + 0.16;
                    }

                    return $score;
                }, 0.35);

                return [
                    ...$candidate,
                    'score' => round(min(0.79, $score), 2),
                    'reason' => 'Matched against available category names and paths.',
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /** @return array{id: int, name: string, path: string, slug: string, is_selectable: bool, has_children: bool, commission_percentage: string} */
    private function optionFromCategory(Category $category): array
    {
        return [
            'id' => (int) $category->getKey(),
            'name' => $category->name,
            'path' => (string) ($category->getAttribute('taxonomy_path') ?: $category->name),
            'slug' => $category->slug,
            'is_selectable' => (bool) $category->is_selectable,
            'has_children' => (int) $category->getAttribute('active_children_count') > 0,
            'commission_percentage' => (string) $category->commission_percentage,
        ];
    }
}
