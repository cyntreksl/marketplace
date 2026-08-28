<?php

namespace App\Services;

use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Models\GoogleProductTaxonomyVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoogleProductTaxonomyImportService
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
        private readonly GoogleProductTaxonomyRepository $taxonomies,
        private readonly TaxonomyCategorySyncService $categorySync,
    ) {}

    /** @return array<int, array{google_product_category_id: int, full_path: string, name: string, parent_path: string|null, depth: int}> */
    public function preview(UploadedFile $file): array
    {
        return $this->previewPath($file->getRealPath());
    }

    /** @return array<int, array{google_product_category_id: int, full_path: string, name: string, parent_path: string|null, depth: int}> */
    public function previewPath(string $path): array
    {
        $rows = [];
        $ids = [];
        $paths = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $number => $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (! preg_match('/^(\\d+)\\s*-\\s*(.+)$/', trim($line), $matches)) {
                throw ValidationException::withMessages(['taxonomy_file' => 'Malformed row at line '.($number + 1).'.']);
            }
            $id = (int) $matches[1];
            $path = trim($matches[2]);
            $parts = array_map(trim(...), explode('>', $path));
            if ($id < 1 || $path === '' || isset($ids[$id]) || isset($paths[$path]) || in_array('', $parts, true)) {
                throw ValidationException::withMessages(['taxonomy_file' => 'Duplicate or invalid row at line '.($number + 1).'.']);
            }
            $ids[$id] = true;
            $paths[$path] = true;
            $rows[] = ['google_product_category_id' => $id, 'full_path' => $path, 'name' => (string) end($parts), 'parent_path' => count($parts) > 1 ? implode(' > ', array_slice($parts, 0, -1)) : null, 'depth' => count($parts) - 1];
        }
        foreach ($rows as $row) {
            if ($row['parent_path'] && ! isset($paths[$row['parent_path']])) {
                throw ValidationException::withMessages(['taxonomy_file' => 'Missing parent path: '.$row['parent_path'].'.']);
            }
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['taxonomy_file' => 'The file has no taxonomy rows.']);
        }

        return $rows;
    }

    public function import(User $actor, UploadedFile $file, string $version, string $locale): GoogleProductTaxonomyVersion
    {
        return $this->importPath($actor, $file->getRealPath(), $file->getClientOriginalName(), $version, $locale);
    }

    public function importPath(
        ?User $actor,
        string $path,
        string $sourceFilename,
        string $version,
        string $locale,
        bool $reuseExisting = false,
    ): GoogleProductTaxonomyVersion {
        $rows = $this->previewPath($path);
        $checksum = hash_file('sha256', $path);
        $existing = $this->taxonomies->findByChecksum($checksum);
        if ($existing !== null) {
            if ($reuseExisting) {
                return $existing;
            }

            throw ValidationException::withMessages(['taxonomy_file' => 'This taxonomy file was already imported.']);
        }

        return DB::transaction(function () use ($actor, $sourceFilename, $version, $locale, $rows, $checksum): GoogleProductTaxonomyVersion {
            $taxonomy = $this->taxonomies->createVersion([
                'version' => $version,
                'locale' => $locale,
                'source_filename' => $sourceFilename,
                'checksum' => $checksum,
                'node_count' => count($rows),
                'imported_by' => $actor?->id,
            ]);
            $nodeIds = [];
            foreach ($rows as $row) {
                $nodeIds[$row['full_path']] = $this->taxonomies->createNode($taxonomy, [
                    'google_product_category_id' => $row['google_product_category_id'],
                    'full_path' => $row['full_path'],
                    'name' => $row['name'],
                    'depth' => $row['depth'],
                    'parent_id' => $row['parent_path'] ? $nodeIds[$row['parent_path']] : null,
                ]);
            }
            $this->auditLogs->record($actor, 'taxonomy.imported', $taxonomy, null, $taxonomy->getAttributes(), 'Imported official Google Product Taxonomy.');

            return $taxonomy;
        });
    }

    public function activate(?User $actor, GoogleProductTaxonomyVersion $taxonomy, string $reason): void
    {
        DB::transaction(function () use ($actor, $taxonomy, $reason): void {
            $this->taxonomies->deactivateActiveVersions();
            $taxonomy = $this->taxonomies->versionWithTrashed((int) $taxonomy->getKey());
            $before = $taxonomy->getAttributes();
            $taxonomy->forceFill(['is_active' => true, 'activated_at' => now()]);
            $this->taxonomies->saveVersion($taxonomy);
            $this->categorySync->synchronize($taxonomy);
            $this->auditLogs->record($actor, 'taxonomy.activated', $taxonomy, $before, $taxonomy->getAttributes(), $reason);
        });
    }

    public function versionFromPath(string $path): string
    {
        $firstLine = file($path, FILE_IGNORE_NEW_LINES)[0] ?? '';
        if (! preg_match('/^# Google_Product_Taxonomy_Version:\s*(.+)$/', trim($firstLine), $matches)) {
            throw ValidationException::withMessages(['taxonomy_file' => 'The taxonomy version header is missing.']);
        }

        return trim($matches[1]);
    }
}
