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

test('the storefront shares footer support and payment details', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('marketplace.support.email', 'support@prodeals.lk')
            ->where('marketplace.support.hours', '09:00–18:00')
            ->where('marketplace.support.days', 'Seven days a week')
            ->where('marketplace.support.timezone', 'Sri Lanka Standard Time (UTC+05:30)')
            ->where('marketplace.payment_methods', [
                'Card payments',
                'Bank transfer',
                'Cash on delivery',
            ]));
});
