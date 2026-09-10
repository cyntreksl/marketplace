import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

let server;
let ProductDetails;
let BuyerOverview;
let BuyerOrderDetail;
let inertiaState;

before(async () => {
    server = await createServer({
        configFile: false,
        envDir: false,
        resolve: {
            alias: {
                '@inertiajs/react': '\0review-flags-inertia',
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        plugins: [
            {
                name: 'review-flags-test-context',
                enforce: 'pre',
                resolveId(source) {
                    if (source === '\0review-flags-inertia') {
                        return source;
                    }

                    if (source.endsWith('/components/buyer-portal-layout')) {
                        return '\0review-flags-buyer-layout';
                    }

                    if (source.endsWith('/components/rich-text-editor')) {
                        return '\0review-flags-rich-text';
                    }
                },
                load(id) {
                    if (id === '\0review-flags-inertia') {
                        return `
                            import { createElement } from 'react';
                            export const state = { product: false };
                            export const usePage = () => ({
                                props: {
                                    auth: { user: null },
                                    reviewFlags: { product: state.product, seller: false },
                                },
                                url: '/buyer',
                            });
                            export const Head = () => null;
                            export const Link = ({ href, children, ...props }) => createElement('a', { ...props, href: href?.url ?? href }, children);
                            export const Form = ({ children, ...props }) => createElement('form', props, typeof children === 'function' ? children({ errors: {}, processing: false }) : children);
                            export const Deferred = ({ children }) => children;
                        `;
                    }

                    if (id === '\0review-flags-buyer-layout') {
                        return 'export const BuyerPortalLayout = ({ children }) => children;';
                    }

                    if (id === '\0review-flags-rich-text') {
                        return 'export const RichTextContent = ({ value }) => value;';
                    }
                },
            },
        ],
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    ({ ProductDetails } = await server.ssrLoadModule(
        '/resources/js/components/product-details.tsx',
    ));
    BuyerOverview = (
        await server.ssrLoadModule('/resources/js/pages/buyer/overview.tsx')
    ).default;
    BuyerOrderDetail = (
        await server.ssrLoadModule('/resources/js/pages/buyer/orders/show.tsx')
    ).default;
    inertiaState = (
        await server.ssrLoadModule('\0review-flags-inertia')
    ).state;
});

after(async () => {
    await server?.close();
});

const listing = {
    title: 'Flagged product',
    description: null,
    media: [],
    brand: null,
    model: null,
    category: null,
    condition: 'new',
    specifications: {},
    warranty: null,
    reviewCount: 1,
    ratingAverage: 5,
};

function renderProductDetails(reviewsEnabled) {
    return renderToStaticMarkup(
        createElement(ProductDetails, {
            listing,
            reviewsEnabled,
            reviews: [
                {
                    id: 1,
                    rating: 5,
                    comment: 'Visible verified feedback',
                    buyerName: 'Test buyer',
                    createdAt: '2026-09-10',
                },
            ],
            questions: [],
            pendingQuestions: [],
            categoryPolicies: null,
        }),
    );
}

test('product review sections follow the product review flag', () => {
    const disabled = renderProductDetails(false);
    const enabled = renderProductDetails(true);

    assert.doesNotMatch(disabled, /Reviews \(1\)/);
    assert.doesNotMatch(disabled, /Visible verified feedback/);
    assert.match(enabled, /Reviews \(1\)/);
    assert.match(enabled, /Visible verified feedback/);
});

test('buyer overview hides feedback activity while product reviews are disabled', () => {
    const summary = {
        order_counts: {},
        pending_feedback_count: 2,
        active_return_count: 0,
        default_shipping_address: null,
        default_billing_address: null,
        security: { two_factor_enabled: false, passkey_count: 0 },
    };

    inertiaState.product = false;
    const disabled = renderToStaticMarkup(
        createElement(BuyerOverview, { summary }),
    );
    inertiaState.product = true;
    const enabled = renderToStaticMarkup(
        createElement(BuyerOverview, { summary }),
    );

    assert.doesNotMatch(disabled, /Awaiting feedback/);
    assert.match(enabled, /Awaiting feedback/);
});

test('buyer orders hide review actions and submitted ratings while disabled', () => {
    const order = {
        number: 'ORD-1001',
        created_at: '2026-09-10T08:00:00Z',
        stage: 'completed',
        stage_label: 'Completed',
        status: 'confirmed',
        subtotal: '1000.00',
        shipping_total: '0.00',
        total: '1000.00',
        shipping_address: null,
        billing_address: null,
        payments: [],
        seller_orders: [
            {
                number: 'PKG-1001',
                store_name: 'Test store',
                status: 'completed',
                cancellation: null,
                refund: null,
                shipment: null,
                items: [
                    {
                        id: 1,
                        title: 'Reviewed product',
                        image_url: null,
                        pricing_tier: 'retail',
                        variant_options: null,
                        quantity: 1,
                        unit_price: '1000.00',
                        total: '1000.00',
                        can_return: false,
                        can_review: true,
                        review: { rating: 5, comment: 'Excellent' },
                    },
                ],
            },
        ],
    };

    inertiaState.product = false;
    const disabled = renderToStaticMarkup(
        createElement(BuyerOrderDetail, { order }),
    );
    inertiaState.product = true;
    const enabled = renderToStaticMarkup(
        createElement(BuyerOrderDetail, { order }),
    );

    assert.doesNotMatch(disabled, /Leave feedback/);
    assert.doesNotMatch(disabled, /5\/5 submitted/);
    assert.match(enabled, /Leave feedback/);
    assert.match(enabled, /5\/5 submitted/);
});
