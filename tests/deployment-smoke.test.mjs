import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

const source = await readFile(
    new URL('../.github/deploy/deploy-production.sh', import.meta.url),
    'utf8',
);
const productSmoke = source.match(/smoke_product\(\) \{[\s\S]*?\n\}/)?.[0];
assert.ok(productSmoke);

test('product smoke checks consume large sitemaps without breaking the pipe', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'prodeals-smoke-'));

    try {
        await writeFile(
            join(directory, 'index.xml'),
            Array.from(
                { length: 5000 },
                (_, index) =>
                    `<loc>https://prodeals.lk/sitemaps/products-${index + 1}.xml</loc>`,
            ).join('\n'),
        );
        await writeFile(
            join(directory, 'products.xml'),
            Array.from(
                { length: 5000 },
                (_, index) =>
                    `<loc>https://prodeals.lk/listings/product-${index + 1}</loc>`,
            ).join('\n'),
        );
        const script = `set -Eeuo pipefail
runtime_dir="$1"
curl() {
    case "\${!#}" in
        https://prodeals.lk/sitemap.xml) cat "$runtime_dir/index.xml" ;;
        https://prodeals.lk/sitemaps/products-1.xml) cat "$runtime_dir/products.xml" ;;
        https://prodeals.lk/listings/product-1) printf '%s' '<h1>Product</h1>LKR<script type="application/ld+json">{}</script>' ;;
        *) return 22 ;;
    esac
}
${productSmoke}
smoke_product
`;
        const result = spawnSync(
            'bash',
            ['-c', script, 'smoke-test', directory],
            {
                encoding: 'utf8',
            },
        );
        assert.equal(result.status, 0, result.stderr);
        assert.match(
            await readFile(join(directory, 'product.html'), 'utf8'),
            /<h1>Product<\/h1>/,
        );
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
});

test('deployment pauses order creation through migrations and activation', () => {
    const stages = source.slice(
        source.indexOf("run_stage 'Upload and prepare releases'"),
    );
    const pause = stages.indexOf("'Pause order creation'");
    const migration = stages.indexOf("'Run database and media migrations'");
    const activate = stages.indexOf("'Activate releases'");
    const verify = stages.indexOf("'Verify hosts'");
    const resume = stages.indexOf("'Resume order creation'");
    assert.ok(
        pause >= 0 &&
            pause < migration &&
            migration < activate &&
            activate < verify &&
            verify < resume,
    );
    const functions = [
        'pause_host',
        'resume_host',
        'pause_order_creation',
        'resume_order_creation',
    ]
        .map(
            (name) =>
                source.match(
                    new RegExp(`${name}\\(\\) \\{[\\s\\S]*?\\n\\}`),
                )?.[0],
        )
        .join('\n');
    const result = spawnSync(
        'bash',
        [
            '-c',
            `set -Eeuo pipefail
ssh_options=(-o BatchMode=yes)
DEPLOY_USER=deploy
WORKER_HOST=worker
RELEASE_ID=release
remote_script=release-script
ssh() { printf '%s\\n' "$*"; }
run_on_hosts() { "$1" web; "$1" worker; }
${functions}
pause_order_creation
[[ "$maintenance_paused" == true ]]
resume_order_creation
[[ "$maintenance_paused" == false ]]
`,
        ],
        { encoding: 'utf8' },
    );
    assert.equal(result.status, 0, result.stderr);
    assert.match(
        result.stdout,
        /deploy@web release-script maintenance-down release/,
    );
    assert.match(
        result.stdout,
        /deploy@worker release-script maintenance-down release/,
    );
    assert.match(result.stdout, /supervisorctl stop prodeals-worker/);
    assert.match(
        result.stdout,
        /deploy@web release-script maintenance-up release/,
    );
    assert.match(
        result.stdout,
        /deploy@worker release-script maintenance-up release/,
    );
});
