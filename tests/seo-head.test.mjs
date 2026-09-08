import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import ts from 'typescript';

const source = await readFile(
    new URL('../resources/js/components/seo-head.tsx', import.meta.url),
    'utf8',
);
const compiled = ts
    .transpileModule(source, {
        compilerOptions: {
            jsx: ts.JsxEmit.ReactJSX,
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    })
    .outputText.replace(
        /from (['"])([^'"]+)\1/g,
        (_, quote, specifier) =>
            `from ${quote}${import.meta.resolve(specifier)}${quote}`,
    );
const { SeoHead } = await import(
    `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);
const render = await createInertiaApp({
    resolve: () => SeoHead,
    serverHead: true,
    title: (title) => title,
    strictMode: true,
});

function seoPayload() {
    return {
        title: 'Coffee Grinder - ProDeals.lk',
        description: 'A coffee grinder for your kitchen.',
        canonicalUrl: 'https://prodeals.lk/listings/coffee-grinder',
        robots: 'index,follow,max-image-preview:large',
        openGraph: {
            siteName: 'ProDeals.lk',
            type: 'product',
            locale: 'en_LK',
            image: 'https://media.prodeals.lk/grinder.webp',
            imageWidth: 1280,
            imageHeight: 1280,
        },
        product: {
            price: '2500.00',
            currency: 'LKR',
            availability: 'in stock',
        },
        jsonLd: [
            {
                '@context': 'https://schema.org',
                '@type': 'Product',
                '@id': 'https://prodeals.lk/listings/coffee-grinder#product',
                name: 'Coffee Grinder',
                brand: { '@type': 'Brand', name: 'Example' },
            },
            { '@context': 'https://schema.org', '@type': 'BreadcrumbList' },
        ],
    };
}

async function renderedHead(seo, head = []) {
    const result = await render(
        {
            component: 'SeoTest',
            props: { seo, head },
            url: seo.canonicalUrl,
            version: 'seo-test',
            clearHistory: false,
            encryptHistory: false,
        },
        renderToString,
    );

    return result.head.join('\n');
}

test('Inertia SSR emits valid direct head elements and one graph per entity', async () => {
    const seo = seoPayload();
    const html = await renderedHead(seo);

    assert.doesNotMatch(html, /Symbol\(|<\/?(?:div|span|body)\b/);

    for (const property of ['price:amount', 'price:currency', 'availability']) {
        assert.equal(
            (
                html.match(new RegExp(`property="product:${property}"`, 'g')) ??
                []
            ).length,
            1,
        );
    }

    assert.equal((html.match(/<title\b/g) ?? []).length, 1);
    assert.equal((html.match(/rel="canonical"/g) ?? []).length, 1);
    const graphs = [...html.matchAll(/<script\b[^>]*>(.*?)<\/script>/gs)].map(
        (match) => JSON.parse(match[1]),
    );
    assert.deepEqual(graphs, seo.jsonLd);

    // Exercise the serverHead provider as well as the React Head provider.
    const merged = await renderedHead(seo, html.split('\n'));
    assert.equal(
        (merged.match(/type="application\/ld\+json"/g) ?? []).length,
        2,
    );
    assert.doesNotMatch(merged, /Symbol\(/);
});

test('Inertia SSR keeps untrusted product text inside its JSON-LD script', async () => {
    const seo = seoPayload();
    const name = '</script><script>alert(1)</script> & < > \u2028\u2029';
    seo.jsonLd[0].name = name;
    const html = await renderedHead(seo);

    assert.doesNotMatch(html, /<script>alert\(1\)/);
    assert.equal((html.match(/<script\b/g) ?? []).length, 2);
    const script = html.match(/<script\b[^>]*>(.*?)<\/script>/s)[1];
    assert.equal(JSON.parse(script).name, name);
});

test('non-product SSR pages omit commerce metadata and retain their own graph', async () => {
    const seo = seoPayload();
    seo.product = null;
    seo.jsonLd = [{ '@context': 'https://schema.org', '@type': 'WebSite' }];
    const html = await renderedHead(seo);

    assert.doesNotMatch(html, /property="product:|Symbol\(/);
    assert.equal((html.match(/type="application\/ld\+json"/g) ?? []).length, 1);
});
