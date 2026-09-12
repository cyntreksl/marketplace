import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

let server;
let ProductDetails;
let ProductEngagement;
let ProductQuestionRow;
let SellerSummary;
let resolveProductSection;

before(async () => {
    server = await createServer({
        configFile: false,
        envDir: false,
        resolve: {
            alias: {
                '@inertiajs/react': '\0product-conversion-inertia',
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        plugins: [
            {
                name: 'product-conversion-test-context',
                enforce: 'pre',
                resolveId(source) {
                    if (source === '\0product-conversion-inertia') {
                        return source;
                    }

                    if (source.endsWith('/components/rich-text-editor')) {
                        return '\0product-conversion-rich-text';
                    }
                },
                load(id) {
                    if (id === '\0product-conversion-inertia') {
                        return `
                            import { createElement } from 'react';
                            export const usePage = () => ({
                                props: { auth: { user: null } },
                                url: '/listings/test-product',
                            });
                            export const Link = ({ href, children, ...props }) => createElement('a', { ...props, href: href?.url ?? href }, children);
                            export const Form = ({ children, ...props }) => createElement('form', props, typeof children === 'function' ? children({ errors: {}, processing: false, recentlySuccessful: false }) : children);
                        `;
                    }

                    if (id === '\0product-conversion-rich-text') {
                        return 'export const RichTextContent = ({ value, ...props }) => createElement("div", props, value); import { createElement } from "react";';
                    }
                },
            },
        ],
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    ({ ProductDetails, ProductQuestionRow, resolveProductSection } =
        await server.ssrLoadModule(
            '/resources/js/components/product-details.tsx',
        ));
    ({ ProductEngagement } = await server.ssrLoadModule(
        '/resources/js/components/product-engagement.tsx',
    ));
    ({ SellerSummary } = await server.ssrLoadModule(
        '/resources/js/components/seller-summary.tsx',
    ));
});

after(async () => {
    await server?.close();
});

const listing = {
    id: 1,
    title: 'Campaign power bank',
    slug: 'campaign-power-bank',
    description: '<p>' + 'Useful product details. '.repeat(140) + '</p>',
    shortDescription: null,
    metaTitle: null,
    metaDescription: null,
    model: 'VD-PB061',
    gtin: null,
    mpn: null,
    condition: 'new',
    listingType: 'buy_now',
    productType: 'simple',
    price: '9000.00',
    salePrice: '7500.00',
    effectivePrice: '7500.00',
    retailEnabled: true,
    wholesaleEnabled: false,
    wholesalePrice: null,
    wholesaleMinimumQuantity: null,
    wholesaleTiers: [],
    discountPercentage: 17,
    ratingAverage: null,
    reviewCount: 0,
    location: 'Colombo',
    warranty: null,
    stockQuantity: 5,
    stockStatus: 'in_stock',
    category: null,
    brand: null,
    media: [],
    seller: null,
    auction: null,
    specifications: {},
    variantOptions: [],
    variants: [],
};

test('engagement metrics honor thresholds and format public totals', () => {
    const sparse = renderToStaticMarkup(
        createElement(ProductEngagement, {
            engagement: { soldCount: 1, watcherCount: 1, viewCount: 9 },
        }),
    );
    const active = renderToStaticMarkup(
        createElement(ProductEngagement, {
            engagement: {
                soldCount: 1200,
                watcherCount: 2,
                viewCount: 10,
            },
        }),
    );

    assert.match(sparse, />1<\/strong> item sold/);
    assert.doesNotMatch(sparse, /watching|views/);
    assert.match(active, />1,200<\/strong> items sold/);
    assert.match(active, />2<\/strong> people watching/);
    assert.match(active, />10<\/strong> views/);
});

test('seller summary uses verified wording and omits account age', () => {
    const html = renderToStaticMarkup(
        createElement(SellerSummary, {
            seller: {
                store_name: 'Campaign Store',
                slug: 'campaign-store',
                logoUrl: null,
                coverUrl: null,
                about: null,
                sellingSince: 'September 2026',
                productCount: 3,
            },
        }),
    );

    assert.match(html, /Verified seller/);
    assert.doesNotMatch(html, /Approved seller|Selling since|September 2026/);
});

test('public store pages retain seller account age', async () => {
    const source = await readFile(
        new URL(
            '../resources/js/pages/storefront/stores/show.tsx',
            import.meta.url,
        ),
        'utf8',
    );

    assert.match(source, /Selling since \{seller\.sellingSince\}/);
});

test('product information renders true tabs, mobile accordions, and long overview disclosure', () => {
    const html = renderToStaticMarkup(
        createElement(ProductDetails, {
            listing,
            reviewsEnabled: true,
            reviews: [],
            questions: [],
            pendingQuestions: [],
            categoryPolicies: null,
        }),
    );

    assert.match(html, /role="tablist"/);
    assert.match(html, /role="tabpanel"/);
    assert.match(html, /aria-controls="overview-mobile-content"/);
    assert.match(html, /Show full overview/);
    assert.match(html, /Back to purchase/);
    assert.match(
        html,
        /The seller has not added a detailed description yet|Useful product details/,
    );
});

test('hash resolution respects optional review sections', () => {
    assert.equal(resolveProductSection('#shipping', true), 'shipping');
    assert.equal(resolveProductSection('#reviews', true), 'reviews');
    assert.equal(resolveProductSection('#reviews', false), 'overview');
    assert.equal(resolveProductSection('#unknown', true), 'overview');
});

test('question rows distinguish verified answers from viewer pending questions', () => {
    const answered = renderToStaticMarkup(
        createElement(ProductQuestionRow, {
            question: {
                id: 1,
                question: 'Does it include a cable?',
                answer: 'Yes, one charging cable is included.',
                askedBy: 'Buyer',
                answeredBy: 'Seller',
                answeredAt: '2026-09-12T10:00:00Z',
            },
        }),
    );
    const pending = renderToStaticMarkup(
        createElement(ProductQuestionRow, {
            pending: true,
            question: {
                id: 2,
                question: 'Will it work with my phone?',
                answer: null,
                askedBy: 'You',
                answeredBy: null,
                answeredAt: null,
            },
        }),
    );

    assert.match(answered, /Verified seller answer/);
    assert.match(answered, /charging cable is included/);
    assert.match(pending, /Your question · awaiting answer/);
    assert.match(pending, /visible only to you/);
});

test('conversion UI source keeps one mobile panel open and supports keyboard tabs', async () => {
    const source = await readFile(
        new URL(
            '../resources/js/components/product-details.tsx',
            import.meta.url,
        ),
        'utf8',
    );

    assert.match(source, /setMobileOpen\(next\)/);
    assert.match(source, /ArrowRight/);
    assert.match(source, /ArrowLeft/);
    assert.match(source, /window\.history\.pushState/);
    assert.match(source, /Technical details/);

    const listingSource = await readFile(
        new URL(
            '../resources/js/pages/storefront/listings/show.tsx',
            import.meta.url,
        ),
        'utf8',
    );

    assert.doesNotMatch(listingSource, /OfferCountdown|Ends in/);
});
