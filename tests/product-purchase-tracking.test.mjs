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

test('add to cart uses the raw LKR unit price and shared server event id', () => {
    assert.deepEqual(
        buildAddToCartParameters(84, undefined, '3490.00', 2, 'add-event-84'),
        {
            currency: 'LKR',
            value: 6980,
            event_id: 'add-event-84',
            items: [
                {
                    item_id: '84',
                    item_group_id: '84',
                    price: 3490,
                    quantity: 2,
                },
            ],
        },
    );
});

test('add to cart uses the variant id while retaining the listing group id', () => {
    assert.deepEqual(buildAddToCartParameters(84, 901, 3490, 1), {
        currency: 'LKR',
        value: 3490,
        items: [
            {
                item_id: '901',
                item_group_id: '84',
                price: 3490,
                quantity: 1,
            },
        ],
    });
});
