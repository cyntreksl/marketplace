import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) =>
    readFile(new URL(`../resources/js/${path}`, import.meta.url), 'utf8');

const card = await read('components/storefront-category-card.tsx');
const home = await read('pages/storefront/home.tsx');
const listings = await read('pages/storefront/listings/index.tsx');

test('category cards show full-bleed square artwork above the name and an arrow, without item counts', () => {
    assert.match(card, /className="aspect-square rounded-none/);
    assert.match(card, /<h3 className="line-clamp-2 min-h-10 /);
    assert.match(card, /<ArrowRight /);
    assert.doesNotMatch(card, /items?_count|itemsCount/);
});

test('category card artwork is zoomed to crop the frame baked into uploaded images', () => {
    assert.match(
        card,
        /imageClassName="scale-\[1\.08\] group-hover:scale-\[1\.13\]"/,
    );
});

test('home and listings category rows share the category card', () => {
    for (const page of [home, listings]) {
        assert.match(
            page,
            /import \{ StorefrontCategoryCard \} from '@\/components\/storefront-category-card';/,
        );
        assert.match(page, /<StorefrontCategoryCard\s/);
    }
});

test('the home category row fits eight cards and view all on one desktop row', () => {
    assert.match(
        home,
        /lg:grid lg:grid-cols-\[repeat\(8,minmax\(0,1fr\)\)_5\.5rem\]/,
    );
    assert.match(home, /popularCategories\.slice\(0, 8\)/);
});
