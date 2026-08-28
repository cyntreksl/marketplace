<?php

namespace App\Repositories;

use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Models\GoogleProductTaxonomyVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentGoogleProductTaxonomyRepository implements GoogleProductTaxonomyRepository
{
    public function versions(array $filters = []): LengthAwarePaginator
    {
        return GoogleProductTaxonomyVersion::query()->withTrashed()->with('importer:id,name')->latest()->paginate(20)->withQueryString();
    }

    public function versionWithTrashed(int $id): GoogleProductTaxonomyVersion
    {
        return GoogleProductTaxonomyVersion::withTrashed()->findOrFail($id);
    }

    public function findByChecksum(string $checksum): ?GoogleProductTaxonomyVersion
    {
        return GoogleProductTaxonomyVersion::withTrashed()->where('checksum', $checksum)->first();
    }

    public function createVersion(array $attributes): GoogleProductTaxonomyVersion
    {
        return GoogleProductTaxonomyVersion::query()->create($attributes);
    }

    public function createNode(GoogleProductTaxonomyVersion $taxonomy, array $attributes): int
    {
        return (int) $taxonomy->nodes()->create($attributes)->getKey();
    }

    public function orderedNodes(GoogleProductTaxonomyVersion $taxonomy): Collection
    {
        return $taxonomy->nodes()->orderBy('depth')->orderBy('id')->get();
    }

    public function activeVersion(): ?GoogleProductTaxonomyVersion
    {
        return GoogleProductTaxonomyVersion::query()->where('is_active', true)->first();
    }

    public function deactivateActiveVersions(): void
    {
        GoogleProductTaxonomyVersion::query()->where('is_active', true)->update(['is_active' => false]);
    }

    public function saveVersion(GoogleProductTaxonomyVersion $taxonomy): void
    {
        if ($taxonomy->trashed()) {
            $taxonomy->restore();
        }

        $taxonomy->save();
    }
}
