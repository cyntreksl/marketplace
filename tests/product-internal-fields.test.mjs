import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

let server;
let ProductInternalFields;
let SellerInternalDetailsForm;
let formState;

before(async () => {
    server = await createServer({
        configFile: false,
        envDir: false,
        resolve: {
            alias: {
                '@inertiajs/react': '\0internal-form-inertia',
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        plugins: [
            {
                name: 'internal-form-test',
                enforce: 'pre',
                resolveId(source) {
                    if (source === '\0internal-form-inertia') {
return '\0internal-form-inertia';
}
                },
                load(id) {
                    if (id === '\0internal-form-inertia') {
return `
                    export const state = {};
                    export function useForm(data) {
                        state.data = data;
                        return { data, errors: {}, processing: false, recentlySuccessful: false,
                            setData(key, value) { data[key] = value; },
                            transform(callback) { state.transform = callback; },
                            patch(url) { state.url = url; state.payload = state.transform(data); }
                        };
                    }
                `;
}
                },
            },
        ],
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });
    ({ ProductInternalFields } = await server.ssrLoadModule(
        '/resources/js/components/product-internal-fields.tsx',
    ));
    ({ SellerInternalDetailsForm } = await server.ssrLoadModule(
        '/resources/js/components/seller-internal-details-form.tsx',
    ));
    formState = (await server.ssrLoadModule('\0internal-form-inertia')).state;
});

after(async () => {
    await server?.close();
});

const props = {
    supplierName: 'Private supplier',
    internalNotes: 'Call first',
    costPrice: '1250.50',
    onSupplierChange() {},
    onNotesChange() {},
    onCostChange() {},
    onVariantCostChange() {},
    errorFor() {},
};

test('simple product fields render saved values and decimal cost constraints', () => {
    const html = renderToStaticMarkup(
        createElement(ProductInternalFields, props),
    );
    assert.match(html, /Customers cannot see these details/);
    assert.match(html, /value="Private supplier"/);
    assert.match(html, />Call first<\/textarea>/);
    assert.match(
        html,
        /type="number"[^>]*id="cost-price"[^>]*min="0"[^>]*max="9999999999.99"[^>]*step="0.01"/,
    );
    assert.match(html, /value="1250.50"/);
});

test('variant costs render separately with errors and without a product-level cost', () => {
    const html = renderToStaticMarkup(
        createElement(ProductInternalFields, {
            ...props,
            variants: [
                { label: 'RED-S', costPrice: '100' },
                { label: 'BLUE-L', costPrice: '200' },
            ],
            errorFor: (field) =>
                field === 'variants.1.cost_price'
                    ? 'Enter a valid cost.'
                    : undefined,
        }),
    );
    assert.match(html, /RED-S — cost price/);
    assert.match(html, /BLUE-L — cost price/);
    assert.match(html, /value="100"/);
    assert.match(html, /value="200"/);
    assert.match(html, /Enter a valid cost/);
    assert.doesNotMatch(html, /id="cost-price"/);
});

test('live simple-product save sends only private fields through the seller route', () => {
    const form = SellerInternalDetailsForm({
        listing: {
            id: 42,
            product_type: 'simple',
            cost_price: '25.00',
            supplier_name: 'Supplier',
            internal_notes: 'Note',
            title: 'Public title',
        },
    });
    form.props.onSubmit({ preventDefault() {} });
    assert.equal(formState.url, '/seller/listings/42/internal-details');
    assert.deepEqual(formState.payload, {
        cost_price: '25.00',
        supplier_name: 'Supplier',
        internal_notes: 'Note',
    });
});

test('live variant save preserves ids and sends changed costs without a product cost', () => {
    const form = SellerInternalDetailsForm({
        listing: {
            id: 42,
            product_type: 'variant',
            variants: [
                { id: 7, sku: 'RED', cost_price: '25.00' },
                { id: 8, sku: 'BLUE', cost_price: '40.00' },
            ],
        },
    });
    form.props.children[0].props.onVariantCostChange(1, '50.25');
    form.props.onSubmit({ preventDefault() {} });
    assert.deepEqual(formState.payload, {
        supplier_name: '',
        internal_notes: '',
        variants: [
            { id: 7, cost_price: '25.00' },
            { id: 8, cost_price: '50.25' },
        ],
    });
});

test('seller product form places internal details at the end of the main section', () => {
    const source = readFileSync(
        fileURLToPath(
            new URL(
                '../resources/js/components/seller-product-form.tsx',
                import.meta.url,
            ),
        ),
        'utf8',
    );
    const salesChannelsPos = source.indexOf('title="Sales Channels"');
    const basicInfoPos = source.indexOf('title="Basic Information"');
    const pricingStockPos = source.indexOf('title="Pricing & Stock"');
    const internalDetailsPos = source.indexOf('<ProductInternalFields');

    assert.ok(salesChannelsPos > 0, 'Sales Channels section should exist');
    assert.ok(
        basicInfoPos > salesChannelsPos,
        'Basic Information should follow Sales Channels',
    );
    assert.ok(
        pricingStockPos > basicInfoPos,
        'Pricing & Stock should follow Basic Information',
    );
    assert.ok(
        internalDetailsPos > pricingStockPos,
        'Internal details section should be at the end, after pricing & stock',
    );
});
