import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const checkoutSource = await readFile(
    new URL('../resources/js/pages/buyer/checkout.tsx', import.meta.url),
    'utf8',
);
const productSource = await readFile(
    new URL(
        '../resources/js/pages/storefront/listings/show.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('guest checkout requires an editable email and keeps marketing opt-in unchecked', () => {
    assert.match(checkoutSource, /name="email"[\s\S]*?required[\s\S]*?readOnly=\{!isGuest\}/);
    assert.match(checkoutSource, /name="marketing_opt_in"[\s\S]*?value="1"/);

    const marketingIndex = checkoutSource.indexOf('name="marketing_opt_in"');
    const marketingInput = checkoutSource.slice(
        checkoutSource.lastIndexOf('<input', marketingIndex),
        checkoutSource.indexOf('/>', marketingIndex) + 2,
    );

    assert.notEqual(marketingIndex, -1);
    assert.doesNotMatch(marketingInput, /defaultChecked|checked=/);
});

test('guest checkout hides every saved-address control', () => {
    assert.match(
        checkoutSource,
        /\{!isGuest && \([\s\S]*?name="save_shipping_address"[\s\S]*?\)\}/,
    );
    assert.match(
        checkoutSource,
        /\{!isGuest && \([\s\S]*?name="save_billing_address"[\s\S]*?\)\}/,
    );
});

test('product pages put purchase assurances below price and defer secondary content', () => {
    const offerIndex = productSource.indexOf('{offerSummary}');
    const trustIndex = productSource.indexOf('Purchase protections');

    assert.notEqual(offerIndex, -1);
    assert.ok(trustIndex > offerIndex);
    assert.match(productSource, /Delivery fee &amp; estimate at checkout/);
    assert.match(productSource, /Cash on Delivery eligible/);
    assert.match(productSource, /-day returns/);
    assert.match(productSource, /Verified seller/);
    assert.match(productSource, /<Deferred[\s\S]*?data="deferredContent"/);
    assert.match(productSource, /Loading product information/);
});
