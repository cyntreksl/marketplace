import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const source = await readFile(
    new URL('../resources/js/pages/storefront/home.tsx', import.meta.url),
    'utf8',
);

test('featured deals and new arrivals use responsive grids capped at three rows', () => {
    const productGrid = source.match(
        /function ProductGrid\([\s\S]*?\n}\n\nfunction Countdown/,
    )?.[0];

    assert.ok(productGrid);
    assert.match(
        productGrid,
        /grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6/,
    );
    assert.match(productGrid, /listings\.slice\(0, 18\)/);
    assert.match(productGrid, /index < 6/);
    assert.match(productGrid, /index < 9/);
    assert.match(productGrid, /'hidden sm:block'/);
    assert.match(productGrid, /'hidden lg:block'/);

    for (const title of ['Featured Deals', 'New Arrivals']) {
        assert.match(
            source,
            new RegExp(`<ProductGrid\\s+title="${title}"[\\s\\S]*?listings=`),
        );
    }
});
