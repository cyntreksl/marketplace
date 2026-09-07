import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

let server;
let sanitizeRichText;

before(async () => {
    server = await createServer({
        configFile: false,
        envDir: false,
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
            },
        },
        plugins: [
            {
                name: 'rich-text-editor-test-context',
                enforce: 'pre',
                resolveId(source) {
                    if (source === '@tinymce/tinymce-react') {
                        return '\0tinymce-react';
                    }
                },
                load(id) {
                    if (id === '\0tinymce-react') {
                        return `
                            import { createElement } from 'react';
                            export const Editor = ({ init }) => createElement('div', {
                                'data-plugins': init.plugins.join(' '),
                                'data-toolbar': init.toolbar,
                                'data-valid-elements': init.valid_elements,
                                'data-paste-as-text': String(init.paste_as_text),
                            });
                        `;
                    }
                },
            },
        ],
        server: { middlewareMode: true, watch: null, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    ({ sanitizeRichText } = await server.ssrLoadModule(
        '/resources/js/components/rich-text-editor.tsx',
    ));
});

after(async () => {
    await server?.close();
});

test('rich text sanitization preserves safe tables and links from web pages', () => {
    const result = sanitizeRichText(
        '<table style="width:100%" onclick="bad()"><thead><tr><th colspan="2" scope="col" style="text-align:center;color:red">Feature</th></tr></thead><tbody><tr><td rowspan="2">Power</td><td>100W</td></tr></tbody></table><span style="font-weight:700;position:fixed">Important</span><a href="https://example.test/item" target="_blank" onclick="bad()">Source</a><a href="javascript:alert(1)">Unsafe</a><script>alert(1)</script>',
    );

    assert.match(
        result,
        /<table><thead><tr><th colspan="2" scope="col" style="text-align: center">/,
    );
    assert.match(result, /<td rowspan="2">Power<\/td>/);
    assert.match(result, /<span style="font-weight: 700">Important<\/span>/);
    assert.match(
        result,
        /<a href="https:\/\/example\.test\/item" target="_blank" rel="noopener noreferrer">Source<\/a>/,
    );
    assert.doesNotMatch(
        result,
        /width:|color:|position:|onclick=|javascript:|<script/,
    );
});

test('the editor enables visual table controls and formatted HTML paste', async () => {
    const source = await readFile(
        fileURLToPath(
            new URL(
                '../resources/js/components/rich-text-editor.tsx',
                import.meta.url,
            ),
        ),
        'utf8',
    );

    assert.match(source, /plugins:\s*\[[\s\S]*?'table'/);
    assert.match(source, /toolbar:\s*\n?\s*'[^']*link table[^']*'/);
    assert.match(
        source,
        /valid_elements:[\s\S]*?'[^']*table[^']*th\[colspan\|rowspan\|scope\|style\][^']*'/,
    );
    assert.match(source, /paste_as_text:\s*false/);
});
