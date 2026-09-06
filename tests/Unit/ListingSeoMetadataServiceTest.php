<?php

use App\Services\ListingSeoMetadataService;

test('generates clean metadata from the first usable product text', function (?string $summary, ?string $description, string $expected) {
    $metadata = (new ListingSeoMetadataService)->generate('Portable &amp; Powerful', $summary, $description);

    expect($metadata)->toBe([
        'meta_title' => 'Portable & Powerful',
        'meta_description' => $expected,
    ]);
})->with([
    'summary first' => ['<p>Compact &amp; light</p><p>All day battery</p>', 'Full description', 'Compact & light All day battery'],
    'empty summary' => [' ', '<p>Full description</p><script>ignore()</script>', 'Full description'],
    'markup only summary' => ['<p>&nbsp;</p>', '<style>ignore</style>Full description', 'Full description'],
    'title fallback' => [null, null, 'Portable & Powerful'],
]);

test('uses explicit metadata and normalizes html and whitespace', function () {
    $metadata = (new ListingSeoMetadataService)->generate('Product title', 'Summary', 'Description', ' Custom   title ', '<p>Custom &amp; clear</p>');

    expect($metadata)->toBe(['meta_title' => 'Custom title', 'meta_description' => 'Custom & clear']);
});

test('limits metadata without breaking multibyte text', function () {
    $metadata = (new ListingSeoMetadataService)->generate(str_repeat('é', 100), str_repeat('é', 200), null);

    expect(mb_strlen($metadata['meta_title']))->toBeLessThanOrEqual(60)
        ->and(mb_strlen($metadata['meta_description']))->toBeLessThanOrEqual(160)
        ->and(mb_check_encoding($metadata['meta_title'], 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($metadata['meta_description'], 'UTF-8'))->toBeTrue();
});

test('does not invent metadata for a draft without usable source text', function () {
    expect((new ListingSeoMetadataService)->generate(null, null, null))
        ->toBe(['meta_title' => '', 'meta_description' => '']);
});
