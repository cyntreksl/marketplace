<?php

namespace App\Services;

use App\Models\Category;
use App\Models\GoogleProductTaxonomyVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoogleProductTaxonomyImportService
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    /** @return array<int, array{google_product_category_id: int, full_path: string, name: string, parent_path: string|null, depth: int}> */
    public function preview(UploadedFile $file): array
    {
        $rows = [];
        $ids = [];
        $paths = [];
        foreach (file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $number => $line) {
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
        $rows = $this->preview($file);
        $checksum = hash_file('sha256', $file->getRealPath());
        if (GoogleProductTaxonomyVersion::withTrashed()->where('checksum', $checksum)->exists()) {
            throw ValidationException::withMessages(['taxonomy_file' => 'This taxonomy file was already imported.']);
        }

        return DB::transaction(function () use ($actor, $file, $version, $locale, $rows, $checksum): GoogleProductTaxonomyVersion {
            $taxonomy = GoogleProductTaxonomyVersion::query()->create(['version' => $version, 'locale' => $locale, 'source_filename' => $file->getClientOriginalName(), 'checksum' => $checksum, 'node_count' => count($rows), 'imported_by' => $actor->id]);
            $nodeIds = [];
            foreach ($rows as $row) {
                $node = $taxonomy->nodes()->create([...$row, 'parent_id' => $row['parent_path'] ? $nodeIds[$row['parent_path']] : null]);
                $nodeIds[$row['full_path']] = $node->id;
            }
            $this->auditLogs->record($actor, 'taxonomy.imported', $taxonomy, null, $taxonomy->getAttributes(), 'Imported official Google Product Taxonomy.');

            return $taxonomy;
        });
    }

    public function activate(User $actor, GoogleProductTaxonomyVersion $taxonomy, string $reason): void
    {
        $mapped = Category::query()->whereNotNull('google_product_category_id')->pluck('google_product_category_id');
        if ($mapped->diff($taxonomy->nodes()->whereIn('google_product_category_id', $mapped)->pluck('google_product_category_id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['taxonomy' => 'The version does not contain every category mapped locally.']);
        }
        DB::transaction(function () use ($actor, $taxonomy, $reason): void {
            GoogleProductTaxonomyVersion::query()->where('is_active', true)->update(['is_active' => false]);
            $before = $taxonomy->getAttributes();
            $taxonomy->forceFill(['is_active' => true, 'activated_at' => now()])->save();
            $this->auditLogs->record($actor, 'taxonomy.activated', $taxonomy, $before, $taxonomy->getAttributes(), $reason);
        });
    }
}
