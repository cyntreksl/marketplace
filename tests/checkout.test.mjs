import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

let server;
let Checkout;
let Payment;
let Review;
let ThankYou;

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
    Payment = (
        await server.ssrLoadModule('/resources/js/pages/buyer/payment.tsx')
    ).default;
    Review = (
        await server.ssrLoadModule('/resources/js/pages/buyer/review.tsx')
    ).default;
    ThankYou = (
        await server.ssrLoadModule('/resources/js/pages/buyer/thank-you.tsx')
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

function renderCheckout(overrides = {}, billingAddress = null) {
    return renderToStaticMarkup(
        createElement(Checkout, {
            savedAddresses: [],
            shippingAddress: null,
            billingAddress,
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

test('checkout defaults billing to shipping and offers an editable alternative', () => {
    const html = renderCheckout();
    assert.match(html, /<input[^>]*type="checkbox"[^>]*checked=""/);
    const billing = html.match(
        /<input[^>]*name="billing_address"[^>]*value="shipping"[^>]*>/,
    )[0];
    assert.doesNotMatch(billing, /disabled=""/);
    assert.match(billing, /checked=""/);
    assert.match(html, /Use a different billing address/);
    assert.match(html, /<fieldset[^>]*disabled=""/);
    assert.doesNotMatch(html, /Need Help\?|Questions\? Our support/);
});

test('a saved different billing address is selected and its fields are restored', () => {
    const html = renderCheckout(
        {},
        {
            recipient_name: 'Accounts Department',
            address_line_one: '20 Hill Road',
            address_line_two: null,
            city: 'Kandy',
            postal_code: '20000',
            phone: '0811234567',
        },
    );
    const billing = html.match(
        /<input[^>]*name="billing_address"[^>]*value="different"[^>]*>/,
    )[0];

    assert.match(billing, /checked=""/);
    assert.doesNotMatch(html, /<fieldset[^>]*disabled=""/);
    assert.match(
        html,
        /name="billing_recipient_name"[^>]*value="Accounts Department"/,
    );
    assert.match(html, /name="billing_city"[^>]*value="Kandy"/);
    assert.match(html, /name="billing_phone"[^>]*value="0811234567"/);
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

test('phone validation requires exactly 10 digits starting with zero', () => {
    const html = renderCheckout();
    const phoneInput = html.match(/<input[^>]*name="phone"[^>]*>/)[0];
    const pattern = phoneInput.match(/pattern="([^"]+)"/)[1];
    const phonePattern = new RegExp(`^(?:${pattern})$`, 'v');

    assert.match(phoneInput, /inputMode="numeric"/);
    assert.match(phoneInput, /maxLength="10"/);
    assert.ok(phonePattern.test('0771234567'));

    for (const phone of [
        '077abc4567',
        '7712345678',
        '077123456',
        '07712345678',
        '077 123 4567',
        '+94771234567',
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

test('the sticky summary includes payment reassurance and has no internal scroll box', () => {
    const html = renderCheckout();
    const summary = html.match(
        /<aside[^>]*id="order-summary"[^>]*>[\s\S]*?<\/aside>/,
    )[0];
    const summaryOpeningTag = summary.match(/^<aside[^>]*>/)[0];

    assert.match(summary, /Total Payable/);
    assert.match(summary, /Continue to Payment/);
    assert.match(summaryOpeningTag, /position:sticky|lg:sticky/);
    assert.match(summaryOpeningTag, /style="top:[1-9][0-9]*px"/);
    assert.doesNotMatch(summaryOpeningTag, /100dvh|calc\(/);
    assert.doesNotMatch(summary, /overflow-y-auto|max-h-|100% Secure Checkout/);
    assert.ok(
        summary.indexOf('Safe, secure and encrypted') >
            summary.indexOf('Continue to Payment'),
    );
});

test('review and confirmation show the chosen billing address without a misleading same-address label', () => {
    const shippingAddress = {
        recipient_name: 'Delivery Recipient',
        address_line_one: '10 Main Road',
        address_line_two: null,
        city: 'Colombo',
        postal_code: null,
        phone: '0771234567',
    };
    const billingAddress = {
        ...shippingAddress,
        recipient_name: 'Accounts Department',
        city: 'Kandy',
    };
    const review = renderToStaticMarkup(
        createElement(Review, {
            cart: {
                items: [item],
                subtotal: '1000',
                shippingTotal: '600',
                total: '1600',
                canCheckout: true,
            },
            shippingAddress,
            billingAddress,
            paymentMethod: 'cod',
            checkoutToken: 'test-token',
            reviewHash: 'test-hash',
        }),
    );
    const order = {
        number: 'PRO123456',
        status: 'confirmed',
        placedAt: null,
        subtotal: '1000',
        shippingTotal: '600',
        total: '1600',
        items: [],
        shippingAddress,
        billingAddress,
        billingSameAsShipping: false,
        payment: null,
    };
    const confirmation = renderToStaticMarkup(
        createElement(ThankYou, { order }),
    );

    for (const html of [review, confirmation]) {
        assert.match(html, /Delivery Recipient/);
        assert.match(html, /Accounts Department/);
        assert.match(html, /Kandy/);
        assert.doesNotMatch(html, /Same as shipping address/);
    }

    assert.match(
        renderToStaticMarkup(
            createElement(ThankYou, {
                order: {
                    ...order,
                    billingAddress: shippingAddress,
                    billingSameAsShipping: true,
                },
            }),
        ),
        /Same as shipping address/,
    );
});

function renderPayment(total, paymentMethods, paymentMethod = null) {
    return renderToStaticMarkup(
        createElement(Payment, {
            paymentMethod,
            shippingAddress: {
                recipient_name: 'Test Buyer',
                address_line_one: '10 Main Road',
                city: 'Colombo',
                phone: '0771234567',
            },
            cart: {
                items: [item],
                subtotal: String(Number(total) - 600),
                shippingTotal: '600.00',
                total,
                canCheckout: true,
                paymentMethods,
            },
        }),
    );
}

test('payment keeps ineligible COD visible, disabled and explained, replacing stale selections', () => {
    const html = renderPayment('5000.01', ['stripe'], 'cod');
    const cod = html.match(/<input[^>]*value="cod"[^>]*>/)[0];
    const stripe = html.match(/<input[^>]*value="stripe"[^>]*>/)[0];

    assert.equal((html.match(/type="radio"/g) ?? []).length, 2);
    assert.match(cod, /disabled=""/);
    assert.doesNotMatch(cod, /checked=""/);
    assert.match(stripe, /checked=""/);
    assert.match(cod, /aria-describedby="cod-description"/);
    assert.match(html, /Unavailable for this order/);
    assert.match(
        html,
        /id="cod-description"[^>]*>Available only for order totals of LKR 5,000 or less, including delivery\./,
    );
});

for (const total of ['4999.99', '5000.00']) {
    test(`payment enables COD at an eligible total of ${total} including delivery`, () => {
        const html = renderPayment(total, ['stripe', 'cod'], 'cod');
        const cod = html.match(/<input[^>]*value="cod"[^>]*>/)[0];

        assert.doesNotMatch(cod, /disabled=""/);
        assert.match(cod, /checked=""/);
        assert.doesNotMatch(html, /Unavailable for this order/);
        assert.match(html, /Pay the total when your delivery arrives/);
    });
}

test('payment cannot continue when no method is eligible but still explains COD', () => {
    const html = renderPayment('5000.01', [], 'cod');

    const cod = html.match(/<input[^>]*value="cod"[^>]*>/)[0];
    assert.match(cod, /disabled=""/);
    assert.match(html, /<button[^>]*disabled=""/);
    assert.match(html, /No payment method is available for this order/);
    assert.match(html, /Unavailable for this order/);
});
