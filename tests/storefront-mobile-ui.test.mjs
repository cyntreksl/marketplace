import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const resourcePath = (path) =>
    fileURLToPath(new URL(`../resources/js/${path}`, import.meta.url));

test('storefront provides a safe-area-aware mobile app navigation', async () => {
    const layout = await readFile(
        resourcePath('components/storefront-layout.tsx'),
        'utf8',
    );
    const navigation = await readFile(
        resourcePath('components/storefront-mobile-navigation.tsx'),
        'utf8',
    );

    assert.match(layout, /showMobileNavigation/);
    assert.match(layout, /<StorefrontMobileNavigation \/>/);
    assert.match(layout, /4\.5rem\+env\(safe-area-inset-bottom\)/);
    assert.match(navigation, /aria-label="Mobile app navigation"/);
    assert.match(navigation, /fixed inset-x-0 bottom-0/);
    assert.match(navigation, /safe-area-inset-bottom/);
    assert.match(navigation, /lg:hidden/);

    for (const label of ['Home', 'Shop', 'Cart', 'Saved', 'Account']) {
        assert.match(navigation, new RegExp(`label: '${label}'`));
    }

    assert.match(navigation, /aria-current=\{isActive \? 'page'/);
});

test('mobile storefront header avoids duplicating bottom navigation', async () => {
    const layout = await readFile(
        resourcePath('components/storefront-layout.tsx'),
        'utf8',
    );

    assert.match(layout, /hidden bg-\[#FF6D00\] text-white lg:block/);
    assert.match(
        layout,
        /ml-auto hidden shrink-0 items-center gap-1 sm:gap-2 lg:flex/,
    );
    assert.match(
        layout,
        /storefront-container hidden items-center gap-5 pb-2 lg:flex/,
    );
});

test('product purchase actions stay docked above mobile navigation', async () => {
    const purchase = await readFile(
        resourcePath('components/product-purchase.tsx'),
        'utf8',
    );

    assert.match(purchase, /aria-label="Quick purchase"/);
    assert.match(
        purchase,
        /bottom-\[calc\(4\.5rem\+env\(safe-area-inset-bottom\)\)\]/,
    );
    assert.match(purchase, /Add to Cart/);
    assert.match(purchase, /Checkout/);
    assert.doesNotMatch(purchase, /IntersectionObserver|showSticky/);
});

test('checkout flow keeps its focused mobile actions unobstructed', async () => {
    const focusedPages = [
        'pages/buyer/checkout.tsx',
        'pages/buyer/review.tsx',
        'pages/buyer/payment.tsx',
        'pages/buyer/thank-you.tsx',
    ];

    for (const page of focusedPages) {
        const source = await readFile(resourcePath(page), 'utf8');
        assert.match(source, /showMobileNavigation=\{false\}/);
    }
});
