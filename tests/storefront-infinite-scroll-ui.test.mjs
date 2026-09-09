import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const resourcePath = (path) =>
    fileURLToPath(new URL(`../resources/js/${path}`, import.meta.url));

test('public product grids use buffered native Inertia infinite scrolling', async () => {
    const grid = await readFile(
        resourcePath('components/storefront-product-grid.tsx'),
        'utf8',
    );

    assert.match(grid, /<InfiniteScroll/);
    assert.match(grid, /data="listings"/);
    assert.match(grid, /buffer=\{800\}/);
    assert.match(grid, /params=\{\{ only: \['seo'\] \}\}/);
    assert.match(grid, /previous=\{\(\{ loading \}\)/);
    assert.match(grid, /next=\{\(\{ loading, hasMore \}\)/);
    assert.match(grid, /Loading more products/);
    assert.match(grid, /All \{listings\.total\}/);
    assert.match(grid, /role="status"/);
    assert.match(grid, /className="col-span-full mt-6"/);
    assert.match(grid, /className="col-span-full mt-8/);
    assert.doesNotMatch(grid, /preserveUrl/);
    assert.doesNotMatch(grid, /onlyNext|onlyPrevious/);
});

test('catalog and seller store share the product grid without legacy pagination', async () => {
    const catalog = await readFile(
        resourcePath('pages/storefront/listings/index.tsx'),
        'utf8',
    );
    const store = await readFile(
        resourcePath('pages/storefront/stores/show.tsx'),
        'utf8',
    );
    const filters = await readFile(
        resourcePath('components/storefront-listing-filters.tsx'),
        'utf8',
    );
    const storefrontTypes = await readFile(
        resourcePath('types/storefront.ts'),
        'utf8',
    );

    for (const page of [catalog, store]) {
        assert.match(page, /StorefrontProductGrid/);
        assert.doesNotMatch(page, /StorefrontPagination/);
    }

    assert.match(catalog, /listings\.data\.length/);
    assert.match(catalog, /trackedListingIds/);
    assert.match(catalog, /untrackedItems/);
    assert.match(catalog, /method="get"/);
    assert.match(store, /storeShow\.form\(seller\.slug\)/);
    assert.match(filters, /method="get"/);
    assert.doesNotMatch(filters, /only:|reset:/);
    assert.match(store, /matching products loaded/);
    assert.doesNotMatch(storefrontTypes, /StorefrontPaginationLink/);
    assert.doesNotMatch(storefrontTypes, /links: StorefrontPaginationLink/);
});
