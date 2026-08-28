<?php

namespace Database\Seeders;

use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Services\TaxonomyCategorySyncService;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(
        GoogleProductTaxonomyRepository $taxonomies,
        TaxonomyCategorySyncService $categorySync,
    ): void {
        $taxonomy = $taxonomies->activeVersion();
        if ($taxonomy !== null) {
            $categorySync->synchronize($taxonomy);
        }
    }
}
