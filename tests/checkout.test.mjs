import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

let server;
let Checkout;

before(async () => {
    server = await createServer({
        configFile: false,
        envDir: false,
        resolve: {
            alias: {
                '@inertiajs/react': '\0checkout-inertia',
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        plugins: [
            {
                name: 'checkout-test-context',
                enforce: 'pre',
                resolveId(source) {
                    if (source === '\0checkout-inertia') {
                        return source;
                    }

                    if (source.endsWith('/components/storefront-layout')) {
                        return '\0checkout-layout';
                    }
                },
                load(id) {
                    if (id === '\0checkout-inertia') {
                        return `
                    import { createElement } from 'react';
                    export const usePage = () => ({ props: { auth: { user: { name: 'Test Buyer', email: 'buyer@example.test' } } } });
                    export const Head = () => null;
                    export const Link = ({ href, children, ...props }) => createElement('a', { ...props, href: href?.url ?? href }, children);
                    export const Form = ({ children, onError, ...props }) => createElement('form', props, children({ errors: {}, processing: false }));
                `;
                    }

                    if (id === '\0checkout-layout') {
                        return `export const StorefrontLayout = ({ children }) => children;`;
                    }
                },
            },
        ],
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });
    Checkout = (
        await server.ssrLoadModule('/resources/js/pages/buyer/checkout.tsx')
    ).default;
});

after(async () => {
    await server?.close();
});

const item = {
    id: 1,
    quantity: 1,
    variant: null,
    listing: {
        title: 'Test product',
        price: '1000',
        sale_price: null,
        media: [],
        seller_profile: { store_name: 'Test store' },
    },
};

function renderCheckout(overrides = {}) {
    return renderToStaticMarkup(
        createElement(Checkout, {
            shippingAddress: null,
            cart: {
                items: [item],
                subtotal: '1000',
                shippingTotal: '600',
                total: '1600',
                canCheckout: true,
                ...overrides,
            },
        }),
    );
}

test('checkout defaults email offers on and locks billing to shipping', () => {
    const html = renderCheckout();
    assert.match(html, /<input[^>]*type="checkbox"[^>]*checked=""/);
    const billing = html.match(/<input[^>]*name="billing_address"[^>]*>/)[0];
    assert.match(billing, /disabled=""/);
    assert.match(billing, /checked=""/);
    assert.doesNotMatch(
        html,
        /Use a different billing address|Need Help\?|Questions\? Our support/,
    );
});

test('unavailable delivery choices are disabled and clearly marked coming soon', () => {
    const html = renderCheckout();
    assert.equal((html.match(/Coming soon/g) ?? []).length, 2);

    for (const value of ['express', 'pickup']) {
        const option = html.match(
            new RegExp(`<input[^>]*value="${value}"[^>]*>`),
        )[0];
        assert.match(option, /disabled=""/);
    }

    assert.doesNotMatch(html, /UNAVAILABLE/);
});

test('phone validation accepts formatted numbers and rejects letters and short numbers', () => {
    const html = renderCheckout();
    const phoneInput = html.match(/<input[^>]*name="phone"[^>]*>/)[0];
    const pattern = phoneInput.match(/pattern="([^"]+)"/)[1];
    const phonePattern = new RegExp(`^(?:${pattern})$`, 'v');

    for (const phone of ['0771234567', '077 123 4567', '+94 (77) 123-4567']) {
        assert.ok(phonePattern.test(phone), phone);
    }

    for (const phone of [
        '077abc4567',
        '1234',
        '077+1234567',
        '1234567890123456',
    ]) {
        assert.ok(!phonePattern.test(phone), phone);
    }
});

test('checkout exposes one payment action and prevents checkout for unavailable carts', () => {
    const html = renderCheckout();
    assert.equal((html.match(/Continue to Payment/g) ?? []).length, 1);
    assert.match(html, /href="#order-summary"/);
    assert.match(html, /LKR 1,600/);
    assert.match(
        renderCheckout({ canCheckout: false }),
        /<button[^>]*type="submit"[^>]*disabled=""/,
    );
    assert.doesNotMatch(
        renderCheckout({ items: [] }),
        /Continue to Payment|View summary/,
    );
});

test('the sticky summary ends after payment controls and has no internal scroll box', () => {
    const html = renderCheckout();
    const summary = html.match(
        /<aside[^>]*id="order-summary"[^>]*>[\s\S]*?<\/aside>/,
    )[0];

    assert.match(summary, /Total Payable/);
    assert.match(summary, /Continue to Payment/);
    assert.doesNotMatch(summary, /overflow-y-auto|max-h-|100% Secure Checkout/);
    assert.ok(html.indexOf('100% Secure Checkout') > html.indexOf('</aside>'));
});
