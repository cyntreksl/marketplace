<?php

use App\Models\SellerProfile;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;

test('seller listing content suggestions use openai response output', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $seller = SellerProfile::factory()->create();
    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.product_content.model' => 'gpt-4o-mini',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output_text' => json_encode([
                'meta_title' => 'iPhone 15 Pro Max in Sri Lanka',
                'meta_description' => 'Shop Apple iPhone 15 Pro Max online in Sri Lanka with trusted ProDeals.lk sellers.',
                'short_description' => 'Apple iPhone 15 Pro Max with premium performance and camera features.',
                'specifications_text' => "Product: Apple iPhone 15 Pro Max\nDetail: Premium Apple smartphone.",
            ]),
        ]),
    ]);

    $this->actingAs($seller->user)
        ->postJson(route('seller.listings.content-suggestions'), [
            'title' => 'Apple iPhone 15 Pro Max',
            'description' => 'Apple iPhone 15 Pro Max with premium performance, excellent camera quality, and durable design for everyday use.',
            'target' => 'seo',
        ])
        ->assertOk()
        ->assertJsonPath('meta_title', 'iPhone 15 Pro Max in Sri Lanka')
        ->assertJsonPath('meta_description', 'Shop Apple iPhone 15 Pro Max online in Sri Lanka with trusted ProDeals.lk sellers.')
        ->assertJsonPath('short_description', '')
        ->assertJsonPath('specifications_text', '');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
        && $request['model'] === 'gpt-4o-mini'
        && $request->hasHeader('Authorization', 'Bearer test-openai-key'));
});

test('seller listing content suggestions fall back when openai output is malformed', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $seller = SellerProfile::factory()->create();
    config(['services.openai.api_key' => 'test-openai-key']);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['output_text' => 'not json']),
    ]);

    $this->actingAs($seller->user)
        ->postJson(route('seller.listings.content-suggestions'), [
            'title' => 'Samsung Galaxy Phone',
            'description' => 'Samsung Galaxy phone with a bright display, dependable battery life, and a responsive camera for daily photos.',
            'target' => 'short_description',
        ])
        ->assertOk()
        ->assertJsonPath('meta_title', '')
        ->assertJsonPath('meta_description', '')
        ->assertJsonPath('specifications_text', '')
        ->assertJson(fn (AssertableJson $json) => $json
            ->whereType('short_description', 'string')
            ->where('short_description', fn (string $value): bool => $value !== '')
            ->etc()
        );
});

test('seller listing content suggestions validate input', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $seller = SellerProfile::factory()->create();

    $this->actingAs($seller->user)
        ->postJson(route('seller.listings.content-suggestions'), [
            'title' => 'TV',
            'description' => 'Too short.',
            'target' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'description', 'target']);
});
