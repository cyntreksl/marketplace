<?php

use App\Jobs\GenerateListingImageVariants;

test('variant jobs use an immutable media version as their unique key', function () {
    $job = new GenerateListingImageVariants(42, 'version-123', true);

    expect($job->uniqueId())->toBe('42:version-123')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60)
        ->and($job->backoff)->toBe([5, 15, 30])
        ->and($job->uniqueFor)->toBe(3600);
});
