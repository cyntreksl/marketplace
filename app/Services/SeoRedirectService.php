<?php

namespace App\Services;

use App\Contracts\Repositories\SeoRedirectRepository;
use App\Models\SeoRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeoRedirectService
{
    public function __construct(private readonly SeoRedirectRepository $redirects) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): SeoRedirect
    {
        return $this->persist(new SeoRedirect, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(SeoRedirect $redirect, array $attributes): SeoRedirect
    {
        return $this->persist($redirect, $attributes);
    }

    public function delete(SeoRedirect $redirect): void
    {
        $this->redirects->delete($redirect);
    }

    public function restore(SeoRedirect $redirect): void
    {
        $this->redirects->restore($redirect);
    }

    public function resolve(string $requestPath): ?string
    {
        $redirect = $this->redirects->activeBySource($this->normalizePath($requestPath));

        if ($redirect === null) {
            return null;
        }

        $this->redirects->recordHit($redirect);

        return $redirect->destination_path;
    }

    public function recordSlugChange(string $routePrefix, ?string $oldSlug, ?string $newSlug): void
    {
        if (! filled($oldSlug) || ! filled($newSlug) || $oldSlug === $newSlug) {
            return;
        }

        $attributes = [
            'source_path' => '/'.trim($routePrefix, '/').'/'.$oldSlug,
            'destination_path' => '/'.trim($routePrefix, '/').'/'.$newSlug,
            'is_active' => true,
        ];
        $existing = $this->redirects->bySource($attributes['source_path']);

        if ($existing !== null) {
            if ($existing->trashed()) {
                $this->redirects->restore($existing);
            }

            $this->update($existing, $attributes);

            return;
        }

        $this->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function persist(SeoRedirect $redirect, array $attributes): SeoRedirect
    {
        $sourcePath = $this->normalizePath((string) ($attributes['source_path'] ?? $redirect->source_path));
        $destinationPath = $this->normalizePath((string) ($attributes['destination_path'] ?? $redirect->destination_path));

        if ($sourcePath === '/' || $sourcePath === $destinationPath) {
            throw ValidationException::withMessages([
                'destination_path' => 'The source and destination must be different internal paths.',
            ]);
        }

        $visited = [$sourcePath];
        $terminalPath = $destinationPath;

        for ($hop = 0; $hop < 20; $hop++) {
            $next = $this->redirects->activeBySource($terminalPath);

            if ($next === null || $next->is($redirect)) {
                break;
            }

            if (in_array($next->destination_path, $visited, true)) {
                throw ValidationException::withMessages([
                    'destination_path' => 'The redirect would create a loop.',
                ]);
            }

            $visited[] = $terminalPath;
            $terminalPath = $next->destination_path;
        }

        return DB::transaction(function () use ($redirect, $attributes, $sourcePath, $terminalPath): SeoRedirect {
            $redirect->fill([
                ...$attributes,
                'source_path' => $sourcePath,
                'destination_path' => $terminalPath,
                'is_active' => (bool) ($attributes['is_active'] ?? $redirect->is_active ?? true),
            ]);
            $this->redirects->save($redirect);
            $this->redirects->collapseDestinations($sourcePath, $terminalPath);

            return $redirect;
        });
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url(trim($path), PHP_URL_PATH);
        $normalized = '/'.trim(is_string($path) ? $path : '', '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
