<?php

namespace Database\Seeders;

use App\Services\GoogleProductTaxonomyImportService;
use Illuminate\Database\Seeder;
use RuntimeException;

class GoogleProductTaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GoogleProductTaxonomyImportService $taxonomyImporter): void
    {
        $path = config('catalog.taxonomy.source_path');
        $checksum = hash_file('sha256', $path);
        if ($checksum !== config('catalog.taxonomy.checksum')) {
            throw new RuntimeException('The bundled Google Product Taxonomy checksum does not match its pinned snapshot.');
        }

        $taxonomy = $taxonomyImporter->importPath(
            actor: null,
            path: $path,
            sourceFilename: basename($path),
            version: $taxonomyImporter->versionFromPath($path),
            locale: config('catalog.taxonomy.locale'),
            reuseExisting: true,
        );

        $taxonomyImporter->activate(null, $taxonomy, 'Activated the bundled Google Product Taxonomy snapshot.');
    }
}
