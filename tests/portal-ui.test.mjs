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

test('seller profile pages share the portal header and avoid nested main landmarks', async () => {
    const storefront = await readFile(
        resourcePath('pages/seller/store.tsx'),
        'utf8',
    );
    const businessProfile = await readFile(
        resourcePath('pages/seller/onboarding.tsx'),
        'utf8',
    );
    const questions = await readFile(
        resourcePath('pages/shared/product-questions.tsx'),
        'utf8',
    );
    const artworkUploader = await readFile(
        resourcePath('components/category-artwork-uploader.tsx'),
        'utf8',
    );

    for (const view of [storefront, businessProfile, questions]) {
        assert.match(view, /SellerPageHeader/);
        assert.doesNotMatch(view, /<main/);
    }

    assert.match(storefront, /<div className="space-y-6">/);
    assert.match(businessProfile, /<div className="space-y-6">/);
    assert.doesNotMatch(storefront, /mx-auto max-w-/);
    assert.doesNotMatch(businessProfile, /mx-auto max-w-/);
    assert.match(questions, /No customer questions found/);
    assert.match(artworkUploader, /const isWideArtwork = aspect >= 3/);
    assert.match(artworkUploader, /sm:grid-cols-\[18rem_minmax\(0,1fr\)\]/);
    assert.match(artworkUploader, /sm:grid-cols-\[9rem_minmax\(0,1fr\)\]/);
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

test('seller overview constrains long low-stock product titles', async () => {
    const overview = await readFile(
        resourcePath('pages/seller/overview.tsx'),
        'utf8',
    );

    assert.match(overview, /className="grid min-w-0 gap-5"/);
    assert.match(overview, /className="min-w-0 truncate font-semibold"/);
    assert.match(overview, /className="shrink-0 text-rose-600"/);
});
