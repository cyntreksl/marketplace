import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const resourcePath = (path) =>
    fileURLToPath(new URL(`../resources/js/${path}`, import.meta.url));

test('shared profile settings do not offer account deletion', async () => {
    const profile = await readFile(
        resourcePath('pages/settings/profile.tsx'),
        'utf8',
    );

    assert.doesNotMatch(profile, /DeleteUser|Delete account/);
});

test('shared profile settings display email as non-editable information', async () => {
    const profile = await readFile(
        resourcePath('pages/settings/profile.tsx'),
        'utf8',
    );
    const emailInput = profile.match(/<Input\s+id="email"[\s\S]*?\/>/)?.[0];

    assert.ok(emailInput);
    assert.match(emailInput, /disabled/);
    assert.doesNotMatch(emailInput, /name="email"/);
    assert.match(profile, /Email address cannot be changed\./);
});

test('cookie preferences use a compact icon at the bottom left', async () => {
    const consentManager = await readFile(
        resourcePath('components/consent-manager.tsx'),
        'utf8',
    );

    assert.match(consentManager, /aria-label="Cookie preferences"/);
    assert.match(consentManager, /<Cookie className="size-5" aria-hidden \/>/);
    assert.match(consentManager, /fixed bottom-3 left-3/);
    assert.match(consentManager, /size-10/);
    assert.doesNotMatch(consentManager, /lg:left-/);
});

test('seller data views provide desktop tables and mobile cards with pagination', async () => {
    const orders = await readFile(
        resourcePath('pages/seller/orders/index.tsx'),
        'utf8',
    );
    const products = await readFile(
        resourcePath('pages/seller/listings/index.tsx'),
        'utf8',
    );
    const pagination = await readFile(
        resourcePath('components/seller-pagination.tsx'),
        'utf8',
    );

    for (const view of [orders, products]) {
        assert.match(view, /<table/);
        assert.match(view, /md:hidden|lg:hidden/);
        assert.match(view, /SellerPagination/);
    }

    assert.match(pagination, /preserveScroll/);
    assert.match(pagination, /preserveState/);
    assert.match(pagination, /Previous/);
    assert.match(pagination, /Next/);
});

test('seller order details expose an accessible journey and contextual actions', async () => {
    const detail = await readFile(
        resourcePath('pages/seller/orders/show.tsx'),
        'utf8',
    );

    assert.match(detail, /Order journey/);
    assert.match(detail, /Courier name/);
    assert.match(detail, /Tracking number/);
    assert.match(detail, /processing\.form/);
    assert.match(detail, /shipped\.form/);
    assert.match(detail, /delivered\.form/);
});
