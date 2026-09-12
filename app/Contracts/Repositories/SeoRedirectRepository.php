<?php

namespace App\Contracts\Repositories;

use App\Models\SeoRedirect;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SeoRedirectRepository
{
    /** @param array{search?: string|null, active?: string|null} $filters
     * @return LengthAwarePaginator<int, SeoRedirect>
     */
    public function paginate(array $filters): LengthAwarePaginator;

    public function activeBySource(string $sourcePath): ?SeoRedirect;

    public function bySource(string $sourcePath): ?SeoRedirect;

    public function withTrashed(int $id): SeoRedirect;

    public function save(SeoRedirect $redirect): SeoRedirect;

    public function delete(SeoRedirect $redirect): void;

    public function restore(SeoRedirect $redirect): void;

    public function recordHit(SeoRedirect $redirect): void;

    public function collapseDestinations(string $sourcePath, string $destinationPath): void;
}
