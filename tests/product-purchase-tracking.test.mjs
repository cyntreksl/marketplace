import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

let server;
let buildAddToCartParameters;

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
    ({ buildAddToCartParameters } = await server.ssrLoadModule(
        '/resources/js/components/product-purchase.tsx',
    ));
});

after(async () => {
    await server?.close();
});

test('add to cart uses the raw LKR unit price without display-label scaling', () => {
    assert.deepEqual(buildAddToCartParameters(84, undefined, '3490.00', 2), {
        currency: 'LKR',
        value: 6980,
        items: [
            {
                item_id: '84',
                item_group_id: '84',
                price: 3490,
                quantity: 2,
            },
        ],
    });
});
