import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

test('the React refresh transform does not import and initialize the app twice', async () => {
    const cacheDir = await mkdtemp(join(tmpdir(), 'marketplace-vite-test-'));
    const server = await createServer({
        configFile: false,
        envDir: false,
        cacheDir,
        root: fileURLToPath(new URL('../', import.meta.url)),
        plugins: [
            react({ babel: { plugins: ['babel-plugin-react-compiler'] } }),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, watch: null },
        appType: 'custom',
    });

    try {
        const result = await server.transformRequest('/resources/js/app.tsx');

        assert.ok(result);
        assert.doesNotMatch(
            result.code,
            /import\s+\*\s+as\s+\S+\s+from\s+["'][^"']*\/resources\/js\/app\.tsx(?:\?[^"']*)?["']/,
        );
    } finally {
        await server.close();
        await rm(cacheDir, { recursive: true, force: true });
    }
});

test('shared portal pages opt out of the authenticated app shell', async () => {
    const app = await readFile(
        fileURLToPath(new URL('../resources/js/app.tsx', import.meta.url)),
        'utf8',
    );

    assert.match(app, /case name\.startsWith\('shared\/'\):/);
});
