<?php

namespace App\Contracts\Repositories;

use App\Models\GoogleProductTaxonomyVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GoogleProductTaxonomyRepository
{
    public function versions(array $filters = []): LengthAwarePaginator;

    public function versionWithTrashed(int $id): GoogleProductTaxonomyVersion;
}
