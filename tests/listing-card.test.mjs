import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(
    new URL('../resources/js/components/listing-card.tsx', import.meta.url),
    'utf8',
);

test('listing cards render the currency label smaller than the price amount', () => {
    assert.match(
        source,
        /<span className="mr-1 text-sm font-semibold tracking-normal">\s*Rs\.\s*<\/span>/,
    );
    assert.match(source, /<ListingPrice value=\{listing\.effectivePrice\} \/>/);
});
