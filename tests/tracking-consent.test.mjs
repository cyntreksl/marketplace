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

    tracking.trackEvent('view_item', { item_id: '1' });
    assert.equal(trackedEvents('view_item').length, 0);

    tracking.saveConsent(true, false);
    tracking.trackEvent('view_item', { item_id: '1' });

    assert.equal(scripts.length, 1);
    assert.equal(
        scripts[0].src,
        'https://www.googletagmanager.com/gtm.js?id=GTM-KTT94R7G',
    );
    assert.deepEqual(trackedEvents('view_item')[0], {
        event: 'view_item',
        eventModel: { item_id: '1' },
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
    tracking.trackEvent('view_item', { item_id: 'marketing-item' });

    assert.deepEqual(trackedEvents('view_item').at(-1), {
        event: 'view_item',
        eventModel: { item_id: 'marketing-item' },
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
