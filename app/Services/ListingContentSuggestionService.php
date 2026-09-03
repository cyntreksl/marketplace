<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class ListingContentSuggestionService
{
    /** @return array{meta_title: string, meta_description: string, short_description: string, specifications_text: string} */
    public function suggest(string $title, string $description, string $target): array
    {
        $title = Str::limit(Str::squish($title), 160, '');
        $description = Str::limit(Str::squish($description), 2500, '');
        $fallback = $this->fallback($title, $description);
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            return $this->targetOnly($fallback, $target);
        }

        try {
            $request = Http::baseUrl((string) config('services.openai.base_url', 'https://api.openai.com/v1'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('services.openai.product_content.timeout', 6)));

            $organization = config('services.openai.organization');
            if (is_string($organization) && $organization !== '') {
                $request = $request->withHeaders(['OpenAI-Organization' => $organization]);
            }

            $response = $request->post('/responses', $this->payload($title, $description, $target));

            if (! $response->successful()) {
                return $this->targetOnly($fallback, $target);
            }

            return $this->targetOnly([
                ...$fallback,
                ...$this->decodedSuggestion($response->json()),
            ], $target);
        } catch (ConnectionException|JsonException) {
            return $this->targetOnly($fallback, $target);
        } catch (Throwable) {
            return $this->targetOnly($fallback, $target);
        }
    }

    /** @return array<string, mixed> */
    private function payload(string $title, string $description, string $target): array
    {
        return [
            'model' => (string) config('services.openai.product_content.model', 'gpt-4o-mini'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => 'Generate marketplace listing content for ProDeals.lk. Return JSON only. Keep copy accurate, shopper-friendly, and SEO-focused for Sri Lanka. Do not invent technical facts that are not supported by the input.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'target' => $target,
                        'product_name' => $title,
                        'full_description' => $description,
                        'limits' => [
                            'meta_title' => 60,
                            'meta_description' => 160,
                            'short_description' => 160,
                            'specifications_text' => 700,
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'listing_content_suggestions',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['meta_title', 'meta_description', 'short_description', 'specifications_text'],
                        'properties' => [
                            'meta_title' => ['type' => 'string'],
                            'meta_description' => ['type' => 'string'],
                            'short_description' => ['type' => 'string'],
                            'specifications_text' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'max_output_tokens' => 700,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{meta_title?: string, meta_description?: string, short_description?: string, specifications_text?: string}
     *
     * @throws JsonException
     */
    private function decodedSuggestion(array $response): array
    {
        $content = $this->responseText($response);
        if ($content === null) {
            return [];
        }

        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return [
            'meta_title' => $this->cleanLimitedText(Arr::get($decoded, 'meta_title'), 60),
            'meta_description' => $this->cleanLimitedText(Arr::get($decoded, 'meta_description'), 160),
            'short_description' => $this->cleanLimitedText(Arr::get($decoded, 'short_description'), 160),
            'specifications_text' => $this->cleanLimitedBlockText(Arr::get($decoded, 'specifications_text'), 700),
        ];
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

    /** @return array{meta_title: string, meta_description: string, short_description: string, specifications_text: string} */
    private function fallback(string $title, string $description): array
    {
        $lead = $this->cleanLimitedText($description, 120);

        return [
            'meta_title' => $this->firstFittingText([
                "{$title} in Sri Lanka | ProDeals.lk",
                "{$title} | ProDeals.lk",
                $title,
            ], 60),
            'meta_description' => $this->firstFittingText([
                "{$title}. {$lead} Shop online in Sri Lanka on ProDeals.lk.",
                "{$lead} Available online in Sri Lanka from trusted sellers on ProDeals.lk.",
                "Buy {$title} online in Sri Lanka from trusted sellers on ProDeals.lk.",
                $lead,
            ], 160),
            'short_description' => $this->cleanLimitedText($lead ?: $description, 160),
            'specifications_text' => $this->fallbackSpecifications($title, $description),
        ];
    }

    /**
     * @param  array{meta_title: string, meta_description: string, short_description: string, specifications_text: string}  $suggestion
     * @return array{meta_title: string, meta_description: string, short_description: string, specifications_text: string}
     */
    private function targetOnly(array $suggestion, string $target): array
    {
        $empty = [
            'meta_title' => '',
            'meta_description' => '',
            'short_description' => '',
            'specifications_text' => '',
        ];

        return match ($target) {
            'seo' => [...$empty, 'meta_title' => $suggestion['meta_title'], 'meta_description' => $suggestion['meta_description']],
            'short_description' => [...$empty, 'short_description' => $suggestion['short_description']],
            'specifications' => [...$empty, 'specifications_text' => $suggestion['specifications_text']],
            default => $empty,
        };
    }

    /** @param array<int, string> $candidates */
    private function firstFittingText(array $candidates, int $maximumLength): string
    {
        foreach ($candidates as $candidate) {
            $cleaned = $this->cleanLimitedText($candidate, $maximumLength);

            if (Str::length($cleaned) <= $maximumLength && $cleaned === Str::squish($candidate)) {
                return $cleaned;
            }
        }

        return $this->cleanLimitedText($candidates[0] ?? '', $maximumLength);
    }

    private function fallbackSpecifications(string $title, string $description): string
    {
        $sentences = collect(preg_split('/(?<=[.!?])\s+/', $description) ?: [])
            ->map(fn (string $sentence): string => $this->cleanLimitedText($sentence, 120))
            ->filter()
            ->take(4)
            ->values();

        return $this->cleanLimitedBlockText(
            collect(["Product: {$title}"])
                ->merge($sentences->map(fn (string $sentence): string => "Detail: {$sentence}"))
                ->implode("\n"),
            700,
        );
    }

    private function cleanLimitedText(mixed $value, int $maximumLength): string
    {
        if (! is_string($value)) {
            return '';
        }

        $cleaned = Str::squish(strip_tags($value));

        if (Str::length($cleaned) <= $maximumLength) {
            return $cleaned;
        }

        $shortened = preg_replace('/\s+\S*$/', '', Str::substr($cleaned, 0, $maximumLength));

        return trim($shortened ?: Str::substr($cleaned, 0, $maximumLength));
    }

    private function cleanLimitedBlockText(mixed $value, int $maximumLength): string
    {
        if (! is_string($value)) {
            return '';
        }

        $cleaned = collect(preg_split('/\R+/', strip_tags($value)) ?: [])
            ->map(fn (string $line): string => Str::squish($line))
            ->filter()
            ->implode("\n");

        if (Str::length($cleaned) <= $maximumLength) {
            return $cleaned;
        }

        $shortened = preg_replace('/\s+\S*$/', '', Str::substr($cleaned, 0, $maximumLength));

        return trim($shortened ?: Str::substr($cleaned, 0, $maximumLength));
    }
}
