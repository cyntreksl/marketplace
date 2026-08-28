<?php

namespace App\Services;

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\GoogleProductTaxonomyRepository;
use App\Models\GoogleProductTaxonomyNode;
use App\Models\GoogleProductTaxonomyVersion;

class TaxonomyCategorySyncService
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly GoogleProductTaxonomyRepository $taxonomies,
    ) {}

    /**
     * Synchronize every permitted node from the active reference taxonomy.
     *
     * @return array{active: int, selectable: int, excluded: int}
     */
    public function synchronize(GoogleProductTaxonomyVersion $taxonomy): array
    {
        $nodes = $this->taxonomies->orderedNodes($taxonomy);
        $excludedGoogleIds = array_fill_keys(config('catalog.taxonomy.excluded_google_ids', []), true);
        $excludedNodeIds = [];
        $childNodeIds = $nodes->pluck('parent_id')->filter()->flip();
        $categoryIdsByNodeId = [];
        $permittedGoogleIds = [];
        $selectableCount = 0;
        $departmentOrder = array_flip(config('catalog.taxonomy.department_order', []));

        foreach ($nodes as $node) {
            if (isset($excludedGoogleIds[$node->google_product_category_id]) || ($node->parent_id !== null && isset($excludedNodeIds[$node->parent_id]))) {
                $excludedNodeIds[$node->id] = true;

                continue;
            }

            $isSelectable = ! isset($childNodeIds[$node->id]);
            $category = $this->catalog->saveMappedCategory(
                googleProductCategoryId: $node->google_product_category_id,
                parentId: $node->parent_id === null ? null : $categoryIdsByNodeId[$node->parent_id],
                name: $this->displayName($node),
                fullPath: $node->full_path,
                isSelectable: $isSelectable,
                sortOrder: $node->parent_id === null ? (($departmentOrder[$node->google_product_category_id] ?? 999) + 1) : 0,
            );

            $categoryIdsByNodeId[$node->id] = (int) $category->getKey();
            $permittedGoogleIds[] = $node->google_product_category_id;
            $selectableCount += (int) $isSelectable;
        }

        $this->catalog->deactivateMappedCategoriesExcept($permittedGoogleIds);

        return [
            'active' => count($permittedGoogleIds),
            'selectable' => $selectableCount,
            'excluded' => count($excludedNodeIds),
        ];
    }

    private function displayName(GoogleProductTaxonomyNode $node): string
    {
        if ($node->parent_id !== null) {
            return $node->name;
        }

        return config('catalog.taxonomy.department_names.'.$node->google_product_category_id, $node->name);
    }
}
