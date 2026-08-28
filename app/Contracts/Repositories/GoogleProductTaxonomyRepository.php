<?php

namespace App\Contracts\Repositories;

use App\Models\GoogleProductTaxonomyNode;
use App\Models\GoogleProductTaxonomyVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GoogleProductTaxonomyRepository
{
    /**
     * @param  array{archived?: string|null}  $filters
     * @return LengthAwarePaginator<int, GoogleProductTaxonomyVersion>
     */
    public function versions(array $filters = []): LengthAwarePaginator;

    public function versionWithTrashed(int $id): GoogleProductTaxonomyVersion;

    public function findByChecksum(string $checksum): ?GoogleProductTaxonomyVersion;

    /** @param array<string, mixed> $attributes */
    public function createVersion(array $attributes): GoogleProductTaxonomyVersion;

    /** @param array<string, mixed> $attributes */
    public function createNode(GoogleProductTaxonomyVersion $taxonomy, array $attributes): int;

    /** @return Collection<int, GoogleProductTaxonomyNode> */
    public function orderedNodes(GoogleProductTaxonomyVersion $taxonomy): Collection;

    public function activeVersion(): ?GoogleProductTaxonomyVersion;

    public function deactivateActiveVersions(): void;

    public function saveVersion(GoogleProductTaxonomyVersion $taxonomy): void;
}
