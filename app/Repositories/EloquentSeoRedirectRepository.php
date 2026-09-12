<?php

namespace App\Repositories;

use App\Contracts\Repositories\SeoRedirectRepository;
use App\Models\SeoRedirect;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentSeoRedirectRepository implements SeoRedirectRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $status = $filters['active'] ?? null;

        return SeoRedirect::query()
            ->when($status === 'archived', fn (Builder $query): Builder => $query->onlyTrashed())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('source_path', 'like', '%'.$search.'%')
                    ->orWhere('destination_path', 'like', '%'.$search.'%')))
            ->when($status === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->latest()
            ->paginate(30)
            ->withQueryString();
    }

    public function activeBySource(string $sourcePath): ?SeoRedirect
    {
        return SeoRedirect::query()
            ->where('source_path', $sourcePath)
            ->where('is_active', true)
            ->first();
    }

    public function bySource(string $sourcePath): ?SeoRedirect
    {
        return SeoRedirect::withTrashed()->where('source_path', $sourcePath)->first();
    }

    public function withTrashed(int $id): SeoRedirect
    {
        return SeoRedirect::withTrashed()->findOrFail($id);
    }

    public function save(SeoRedirect $redirect): SeoRedirect
    {
        $redirect->save();

        return $redirect;
    }

    public function delete(SeoRedirect $redirect): void
    {
        $redirect->delete();
    }

    public function restore(SeoRedirect $redirect): void
    {
        $redirect->restore();
    }

    public function recordHit(SeoRedirect $redirect): void
    {
        SeoRedirect::query()->whereKey($redirect->getKey())->increment('hit_count', 1, ['last_hit_at' => now()]);
    }

    public function collapseDestinations(string $sourcePath, string $destinationPath): void
    {
        SeoRedirect::query()
            ->where('is_active', true)
            ->where('destination_path', $sourcePath)
            ->update(['destination_path' => $destinationPath]);
    }
}
