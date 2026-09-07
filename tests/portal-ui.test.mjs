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

test('shared profile settings display email as non-editable information', async () => {
    const profile = await readFile(
        resourcePath('pages/settings/profile.tsx'),
        'utf8',
    );
    const emailInput = profile.match(/<Input\s+id="email"[\s\S]*?\/>/)?.[0];

    assert.ok(emailInput);
    assert.match(emailInput, /disabled/);
    assert.doesNotMatch(emailInput, /name="email"/);
    assert.match(profile, /Email address cannot be changed\./);
});

test('cookie preferences use a compact icon at the bottom left', async () => {
    const consentManager = await readFile(
        resourcePath('components/consent-manager.tsx'),
        'utf8',
    );

    assert.match(consentManager, /aria-label="Cookie preferences"/);
    assert.match(consentManager, /<Cookie className="size-5" aria-hidden \/>/);
    assert.match(consentManager, /fixed bottom-3 left-3/);
    assert.match(consentManager, /size-10/);
    assert.doesNotMatch(consentManager, /lg:left-/);
});
