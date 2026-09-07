<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('app.debug', false);

    Route::get('/_test/http-error/{status}', function (int $status): never {
        abort($status);
    });
});

test('browser exceptions use the branded error page', function (int $status) {
    $this->get("/_test/http-error/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn ($page) => $page
            ->component('errors/http-error')
            ->where('status', $status));
})->with([
    'bad request' => 400,
    'unauthorized' => 401,
    'forbidden' => 403,
    'not found' => 404,
    'method not allowed' => 405,
    'request timeout' => 408,
    'conflict' => 409,
    'gone' => 410,
    'payload too large' => 413,
    'URI too long' => 414,
    'unsupported media type' => 415,
    'page expired' => 419,
    'unprocessable content' => 422,
    'locked' => 423,
    'too early' => 425,
    'too many requests' => 429,
    'request header fields too large' => 431,
    'unavailable for legal reasons' => 451,
    'server error' => 500,
    'not implemented' => 501,
    'bad gateway' => 502,
    'service unavailable' => 503,
    'gateway timeout' => 504,
    'HTTP version not supported' => 505,
    'insufficient storage' => 507,
    'loop detected' => 508,
    'network authentication required' => 511,
]);

test('unknown error statuses use the branded fallback page', function () {
    $this->get('/_test/http-error/520')
        ->assertStatus(520)
        ->assertInertia(fn ($page) => $page
            ->component('errors/http-error')
            ->where('status', 520));
});

test('client errors use the branded page when debug mode is enabled', function () {
    config()->set('app.debug', true);

    $this->get('/_test/http-error/404')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('errors/http-error')
            ->where('status', 404));
});

test('server errors keep the detailed exception page when debug mode is enabled', function () {
    config()->set('app.debug', true);

    $this->get('/_test/http-error/500')
        ->assertServerError()
        ->assertDontSee('Something went off track');
});

test('JSON exceptions keep their JSON response', function () {
    $this->getJson('/_test/http-error/404')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
