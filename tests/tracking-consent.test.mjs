import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

let server;
let tracking;
const cookies = new Map();
const scripts = [];
const sessionValues = new Map();
let reloadCount = 0;

function trackedEvents(event) {
    return window.dataLayer.filter(
        (value) => !Array.isArray(value) && value.event === event,
    );
}

before(async () => {
    globalThis.document = {
        get cookie() {
            return [...cookies.entries()]
                .map(([name, value]) => `${name}=${value}`)
                .join('; ');
        },
        set cookie(value) {
            const [pair] = value.split(';');
            const separator = pair.indexOf('=');
            const name = pair.slice(0, separator);
            const content = pair.slice(separator + 1);

            if (value.includes('Max-Age=0')) {
                cookies.delete(name);
            } else {
                cookies.set(name, content);
            }
        },
        title: 'Test page',
        documentElement: { dataset: { environment: 'production' } },
        head: { append: (script) => scripts.push(script) },
        createElement: () => ({ async: false, dataset: {}, src: '' }),
        querySelector: (selector) =>
            selector === 'script[data-prodeals-gtm]' && scripts.length
                ? scripts[0]
                : null,
    };
    globalThis.window = {
        location: {
            protocol: 'https:',
            origin: 'https://prodeals.test',
            hostname: 'prodeals.test',
            href: 'https://prodeals.test/listings',
            reload: () => {
                reloadCount++;
            },
        },
        sessionStorage: {
            getItem: (key) => sessionValues.get(key) ?? null,
            setItem: (key, value) => sessionValues.set(key, value),
            removeItem: (key) => sessionValues.delete(key),
            key: (index) => [...sessionValues.keys()][index] ?? null,
            get length() {
                return sessionValues.size;
            },
        },
    };
    server = await createServer({
        configFile: false,
        envDir: false,
        define: {
            'import.meta.env.VITE_GTM_CONTAINER_ID':
                JSON.stringify('GTM-KTT94R7G'),
        },
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });
    tracking = await server.ssrLoadModule('/resources/js/lib/tracking.ts');
});

after(async () => {
    await server?.close();
    delete globalThis.document;
    delete globalThis.window;
});

test('GTM and commerce events remain gated until versioned consent is granted', () => {
    tracking.initializeTracking();

    assert.equal(scripts.length, 0);
    assert.equal(Array.isArray(window.dataLayer[0]), false);
    assert.equal(Object.prototype.toString.call(window.dataLayer[0]), '[object Arguments]');
    assert.deepEqual(window.dataLayer[0][2], {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
        functionality_storage: 'granted',
        security_storage: 'granted',
        wait_for_update: 500,
    });

    tracking.trackEvent('view_item', { items: [{ item_id: '1' }] });
    assert.equal(trackedEvents('view_item').length, 0);

    tracking.saveConsent(true, false);
    tracking.trackEvent('view_item', { items: [{ item_id: '1' }] });

    assert.equal(scripts.length, 1);
    assert.equal(
        scripts[0].src,
        'https://www.googletagmanager.com/gtm.js?id=GTM-KTT94R7G',
    );
    assert.deepEqual(trackedEvents('view_item')[0], {
        event: 'view_item',
        eventModel: { currency: 'LKR', items: [{ item_id: '1' }] },
    });
});

test('the data layer rejects non-commerce events and strips PII', () => {
    tracking.trackEvent('select_item', { item_id: '2' });
    tracking.trackEvent('add_to_cart', {
        currency: 'LKR',
        value: 1200,
        event_id: 'AddToCart:test-event',
        email: 'customer@example.com',
        user_data: { email: 'customer@example.com' },
        items: [
            {
                item_id: 12,
                item_group_id: '10',
                price: 1200,
                quantity: 1,
                phone: '0771234567',
            },
        ],
    });
    tracking.trackEvent('add_to_cart', {
        currency: 'LKR',
        value: 1200,
        event_id: 'AddToCart:test-event',
    });

    assert.equal(trackedEvents('select_item').length, 0);
    assert.equal(trackedEvents('add_to_cart').length, 1);
    assert.deepEqual(trackedEvents('add_to_cart')[0], {
        event: 'add_to_cart',
        eventModel: {
            currency: 'LKR',
            value: 1200,
            event_id: 'AddToCart:test-event',
            items: [
                {
                    item_group_id: '10',
                    price: 1200,
                    quantity: 1,
                },
            ],
        },
    });
});

test('purchase events are deduplicated by stable transaction ID', () => {
    tracking.trackPurchase('SO-100', { value: 1600, currency: 'LKR' });
    tracking.trackPurchase('SO-100', { value: 1600, currency: 'LKR' });

    assert.equal(trackedEvents('purchase').length, 1);
    assert.deepEqual(trackedEvents('purchase')[0], {
        event: 'purchase',
        eventModel: {
            value: 1600,
            currency: 'LKR',
            event_id: 'Purchase:SO-100',
            transaction_id: 'SO-100',
        },
    });
});

test('purchase waits in session until consent is granted and then delivers once', () => {
    cookies.delete(tracking.consentCookieName);
    sessionValues.clear();
    const purchaseCount = trackedEvents('purchase').length;

    tracking.trackPurchase('PRO-PENDING', {
        value: 2800,
        currency: 'LKR',
        email: 'must-not-be-stored@example.com',
        items: [{ item_id: '42', price: 2200, quantity: 1 }],
    });

    const pending = sessionValues.get(
        'prodeals.pending_purchase.PRO-PENDING',
    );
    assert.equal(trackedEvents('purchase').length, purchaseCount);
    assert.equal(typeof pending, 'string');
    assert.equal(pending.includes('must-not-be-stored@example.com'), false);

    tracking.saveConsent(false, false);
    assert.equal(trackedEvents('purchase').length, purchaseCount);
    assert.equal(
        sessionValues.has('prodeals.pending_purchase.PRO-PENDING'),
        true,
    );

    tracking.saveConsent(true, true);
    tracking.trackPurchase('PRO-PENDING', { value: 2800 });

    assert.equal(trackedEvents('purchase').length, purchaseCount + 1);
    assert.equal(
        sessionValues.has('prodeals.pending_purchase.PRO-PENDING'),
        false,
    );
    assert.equal(sessionValues.get('prodeals.purchase.PRO-PENDING'), '1');
});

test('tracking initialization recovers a consent-blocked purchase after reload', () => {
    cookies.delete(tracking.consentCookieName);
    sessionValues.clear();
    const purchaseCount = trackedEvents('purchase').length;

    tracking.trackPurchase('PRO-RELOAD', {
        value: 2800,
        currency: 'LKR',
    });
    cookies.set(
        tracking.consentCookieName,
        encodeURIComponent(
            JSON.stringify({
                version: 1,
                analytics: true,
                marketing: true,
                decidedAt: '2026-09-13T05:30:00.000Z',
            }),
        ),
    );

    tracking.initializeTracking();
    tracking.initializeTracking();

    assert.equal(trackedEvents('purchase').length, purchaseCount + 1);
    assert.deepEqual(trackedEvents('purchase').at(-1), {
        event: 'purchase',
        eventModel: {
            value: 2800,
            currency: 'LKR',
            event_id: 'Purchase:PRO-RELOAD',
            transaction_id: 'PRO-RELOAD',
        },
    });
    assert.equal(
        sessionValues.has('prodeals.pending_purchase.PRO-RELOAD'),
        false,
    );
});

test('checkout event models use the confirmed cart values', () => {
    const cart = {
        total: '4690.00',
        items: [
            {
                listing_id: 65,
                listing_variant_id: 901,
                quantity: 2,
                unitPrice: '2045.00',
                listing: { title: 'Smartwatch' },
            },
        ],
    };

    assert.deepEqual(
        tracking.buildCheckoutEventModel(cart, {
            payment_type: 'cod',
        }),
        {
            currency: 'LKR',
            value: 4690,
            items: [
                {
                    item_id: '901',
                    item_group_id: '65',
                    item_name: 'Smartwatch',
                    price: 2045,
                    quantity: 2,
                },
            ],
            payment_type: 'cod',
        },
    );
});

test('local environments never load the production GTM container', () => {
    const loadedScripts = scripts.splice(0);
    document.documentElement.dataset.environment = 'local';

    tracking.saveConsent(true, true);

    assert.equal(scripts.length, 0);

    document.documentElement.dataset.environment = 'production';
    scripts.push(...loadedScripts);
});

test('revocation denies consent, clears known vendor cookies, and reloads', () => {
    cookies.set('_ga', 'analytics-cookie');
    cookies.set('_fbp', 'marketing-cookie');

    tracking.revokeConsent();

    assert.deepEqual(tracking.readConsent(), {
        version: 1,
        analytics: false,
        marketing: false,
        decidedAt: tracking.readConsent().decidedAt,
    });
    assert.equal(cookies.has('_ga'), false);
    assert.equal(cookies.has('_fbp'), false);
    assert.equal(reloadCount, 1);
});

test('marketing-only consent makes commerce events available to consent-checked tags', () => {
    tracking.saveConsent(false, true);
    tracking.trackEvent('view_item', {
        items: [{ item_id: 'marketing-item' }],
    });

    assert.deepEqual(trackedEvents('view_item').at(-1), {
        event: 'view_item',
        eventModel: {
            currency: 'LKR',
            items: [{ item_id: 'marketing-item' }],
        },
    });
});

test('catalog items always use the exported listing or variant id', () => {
    assert.deepEqual(
        tracking.buildCatalogItem(65, null, {
            item_name: 'Food chopper',
        }),
        {
            item_name: 'Food chopper',
            item_id: '65',
            item_group_id: '65',
        },
    );
    assert.deepEqual(tracking.buildCatalogItem(65, 901), {
        item_id: '901',
        item_group_id: '65',
    });
});

test('Meta event ids are included only when a server event id is available', () => {
    assert.deepEqual(
        tracking.withMetaEventId({ currency: 'LKR', value: 3490 }, 'event-84'),
        {
            currency: 'LKR',
            value: 3490,
            event_id: 'event-84',
        },
    );
    assert.deepEqual(
        tracking.withMetaEventId({ currency: 'LKR', value: 3490 }, null),
        {
            currency: 'LKR',
            value: 3490,
        },
    );
});
