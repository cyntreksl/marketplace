import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const resourcePath = (path) =>
    fileURLToPath(new URL(`../resources/js/${path}`, import.meta.url));

test('shared profile settings do not offer account deletion', async () => {
    const profile = await readFile(
        resourcePath('pages/settings/profile.tsx'),
        'utf8',
    );

    assert.doesNotMatch(profile, /DeleteUser|Delete account/);
});

test('cookie preferences clear the desktop buyer portal sidebar', async () => {
    const consentManager = await readFile(
        resourcePath('components/consent-manager.tsx'),
        'utf8',
    );

    assert.match(consentManager, /lg:left-\[292px\]/);
});
