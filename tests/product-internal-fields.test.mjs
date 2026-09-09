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
let EditSellerListing;
let SellerListings;

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
                    if (source.endsWith('/components/seller-portal-layout')) {
                        return '\0seller-layout';
                    }

                    if (source.endsWith('/components/seller-product-form')) {
                        return '\0full-product-form';
                    }

                    if (source === '\0internal-form-inertia') {
                        return '\0internal-form-inertia';
                    }
                },
                load(id) {
                    if (id === '\0seller-layout') {
                        return 'export const SellerPortalLayout = ({children}) => children;';
                    }

                    if (id === '\0full-product-form') {
                        return 'export const SellerProductForm = () => "FULL_PRODUCT_FORM";';
                    }

                    if (id === '\0internal-form-inertia') {
                        return `
                    import { createElement } from 'react';
                    export const Head = () => null;
                    export const Link = ({ href, children, ...props }) => createElement('a', { ...props, href: href?.url ?? href }, children);
                    export const Form = ({ children, ...props }) => createElement('form', props, typeof children === 'function' ? children({ errors: {}, processing: false }) : children);
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
    EditSellerListing = (
        await server.ssrLoadModule(
            '/resources/js/pages/seller/listings/edit.tsx',
        )
    ).default;
    SellerListings = (
        await server.ssrLoadModule(
            '/resources/js/pages/seller/listings/index.tsx',
        )
    ).default;
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

test('seller product form places internal details below both columns and above save actions', () => {
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
    const sidebarEnd = source.indexOf('</aside>');
    const saveActions = source.indexOf('className="sticky bottom-3');

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
        'Internal details section should follow pricing & stock',
    );
    assert.ok(
        internalDetailsPos > sidebarEnd,
        'Internal details follows images, status, and SEO on all screen sizes',
    );
    assert.ok(
        internalDetailsPos < saveActions,
        'Save actions follow internal details',
    );
});

test('approved products expose Edit and render only the internal editor', () => {
    const listing = {
        id: 42,
        title: 'Approved shirt',
        status: 'approved',
        product_type: 'simple',
        cost_price: '25.00',
        supplier_name: 'Supplier',
        internal_notes: 'Private note',
    };
    const indexHtml = renderToStaticMarkup(
        createElement(SellerListings, {
            sellerStatus: 'approved',
            listings: {
                data: [listing],
                links: [],
                current_page: 1,
                last_page: 1,
                total: 1,
            },
            filters: { q: '', status: 'all', sort: 'newest' },
        }),
    );
    assert.match(
        indexHtml,
        /href="[^"]*\/seller\/listings\/42\/edit"[^>]*>Edit<\/a>/,
    );
    const editHtml = renderToStaticMarkup(
        createElement(EditSellerListing, {
            listing,
            selectedCategory: null,
            brands: [],
            sellerStatus: 'approved',
        }),
    );
    assert.match(editHtml, /Internal details/);
    assert.match(editHtml, /Save internal details/);
    assert.doesNotMatch(editHtml, /FULL_PRODUCT_FORM/);
});

test('draft edit retains the full product form and archived listings remain read-only', () => {
    const props = {
        selectedCategory: null,
        brands: [],
        sellerStatus: 'approved',
    };
    const draftHtml = renderToStaticMarkup(
        createElement(EditSellerListing, {
            ...props,
            listing: { id: 42, title: 'Draft', status: 'draft' },
        }),
    );
    assert.match(draftHtml, /FULL_PRODUCT_FORM/);
    const archivedHtml = renderToStaticMarkup(
        createElement(EditSellerListing, {
            ...props,
            listing: { id: 42, title: 'Archived', status: 'archived' },
        }),
    );
    assert.match(archivedHtml, /Archived products are read-only/);
    assert.doesNotMatch(
        archivedHtml,
        /FULL_PRODUCT_FORM|Save internal details/,
    );
});
