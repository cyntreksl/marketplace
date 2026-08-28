<?php

use Inertia\Testing\AssertableInertia as Assert;

test('guests can view every public marketplace content page', function (string $routeName, string $document) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/content/show')
            ->where('document', $document)
            ->where('marketplace.legal_entity.name', 'CYNTREK SOLUTIONS PVT LTD')
            ->where('marketplace.legal_entity.company_number', '16330229')
            ->where('marketplace.support.email', 'support@prodeals.lk'));
})->with([
    'about' => ['about', 'about'],
    'contact' => ['contact', 'contact'],
    'help centre' => ['help', 'help'],
    'frequently asked questions' => ['faq', 'faq'],
    'buying guide' => ['buying', 'buying'],
    'selling guide' => ['selling', 'selling'],
    'shipping policy' => ['policies.shipping', 'shipping'],
    'returns and refunds policy' => ['policies.returns', 'returns'],
    'terms and conditions' => ['legal.terms', 'terms'],
    'privacy notice' => ['legal.privacy', 'privacy'],
    'cookie policy' => ['legal.cookies', 'cookies'],
    'seller policy' => ['policies.sellers', 'sellers'],
    'prohibited items policy' => ['policies.prohibited', 'prohibited'],
]);

test('social profile placeholders stay unconfigured until real links are supplied', function () {
    expect(array_filter(config('marketplace.social_urls')))->toBeEmpty();
});
