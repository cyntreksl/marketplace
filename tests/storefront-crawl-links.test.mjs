import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

let server;
let CategoryStrip;
let CatalogPagination;

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
    ({ CategoryStrip, CatalogPagination } = await server.ssrLoadModule(
        '/resources/js/pages/storefront/listings/index.tsx',
    ));
});

after(async () => {
    await server?.close();
});

test('category strip renders an ordinary link for every available child', () => {
    const children = Array.from({ length: 12 }, (_, index) => ({
        id: index + 1,
        name: `Child ${index + 1}`,
        slug: `child-${index + 1}`,
        image_url: null,
        has_children: false,
    }));
    const html = renderToStaticMarkup(
        createElement(CategoryStrip, {
            categories: [],
            categoryContext: {
                current: { id: 1, name: 'Parent', slug: 'parent' },
                ancestors: [],
                children,
            },
            browseUrl: '/listings',
            catalogMode: 'retail',
        }),
    );

    assert.equal((html.match(/href="\/categories\/child-/g) ?? []).length, 12);
    assert.match(html, /href="\/categories\/child-12"/);
});

test('catalog pagination renders previous and next HTML links', () => {
    const html = renderToStaticMarkup(
        createElement(CatalogPagination, {
            listings: {
                current_page: 2,
                last_page: 3,
                prev_page_url: 'https://prodeals.lk/listings?page=1',
                next_page_url: 'https://prodeals.lk/listings?page=3',
            },
        }),
    );

    assert.match(html, /<a[^>]*rel="prev"[^>]*href="https:\/\/prodeals\.lk\/listings\?page=1"/);
    assert.match(html, /<a[^>]*rel="next"[^>]*href="https:\/\/prodeals\.lk\/listings\?page=3"/);
    assert.match(html, /Page 2 of 3/);
});
