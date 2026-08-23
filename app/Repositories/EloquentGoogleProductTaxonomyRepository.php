<?php

namespace App\Repositories;

use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Models\GoogleProductTaxonomyVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
