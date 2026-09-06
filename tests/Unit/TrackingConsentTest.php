<?php

use App\Support\TrackingConsent;

test('valid versioned consent cookies are parsed', function () {
    $cookie = urlencode(json_encode([
        'version' => 1,
        'analytics' => true,
        'marketing' => false,
        'decidedAt' => '2026-09-07T10:00:00.000Z',
    ], JSON_THROW_ON_ERROR));
    $consent = (new TrackingConsent)->fromCookie($cookie);

    expect($consent)->toMatchArray([
        'version' => 1,
        'analytics' => true,
        'marketing' => false,
    ]);
});

test('invalid or outdated consent cookies are rejected', function () {
    $tracking = new TrackingConsent;

    expect($tracking->fromCookie('not-json'))->toBeNull()
        ->and($tracking->fromCookie(urlencode('{"version":2,"analytics":true,"marketing":true,"decidedAt":"now"}')))->toBeNull();
});
