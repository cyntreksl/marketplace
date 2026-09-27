import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

let server;
let deliveryDetails;

before(async () => {
    server = await createServer({
        configFile: false,
        envDir: false,
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });
    deliveryDetails = await server.ssrLoadModule(
        '/resources/js/lib/delivery-details.ts',
    );
});

after(async () => {
    await server?.close();
});

test('delivery details are claimed only once for the browser', () => {
    const values = new Map();
    const storage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };

    assert.equal(deliveryDetails.claimDeliveryDetailsModal(storage), true);
    assert.equal(values.get(deliveryDetails.deliveryDetailsStorageKey), '1');
    assert.equal(deliveryDetails.claimDeliveryDetailsModal(storage), false);
});

test('delivery details remain available when browser storage is blocked', () => {
    const storage = {
        getItem: () => {
            throw new Error('blocked');
        },
        setItem: () => {
            throw new Error('blocked');
        },
    };

    assert.equal(deliveryDetails.claimDeliveryDetailsModal(storage), true);
});

test('the shared storefront layout mounts the accessible delivery modal', async () => {
    const [layout, modal] = await Promise.all([
        readFile(
            new URL(
                '../resources/js/components/storefront-layout.tsx',
                import.meta.url,
            ),
            'utf8',
        ),
        readFile(
            new URL(
                '../resources/js/components/delivery-details-modal.tsx',
                import.meta.url,
            ),
            'utf8',
        ),
    ]);

    assert.match(layout, /<DeliveryDetailsModal \/>/);
    assert.match(modal, /<DialogTitle/);
    assert.match(modal, /<DialogDescription/);
    assert.match(modal, /claimDeliveryDetailsModal\(window\.localStorage\)/);
    assert.match(modal, /Start Shopping/);
});

test('desktop artwork is responsive while the offer and action remain HTML', async () => {
    const modal = await readFile(
        new URL('../resources/js/components/delivery-details-modal.tsx', import.meta.url),
        'utf8',
    );

    assert.match(modal, /lg:max-w-\[1000px\]/);
    assert.match(modal, /media="\(min-width: 1024px\)"/);
    assert.match(modal, /srcSet=\{deliveryArtworkUrl\}/);
    assert.match(modal, /Rs\. 200/);
    assert.match(modal, /href=\{listingsIndex\(\)\}/);
    assert.match(modal, /onClick=\{\(\) => setOpen\(false\)\}/);
    assert.doesNotMatch(modal, /clipPath|function DeliveryIllustration/);
});
