<?php

use App\Services\CheckoutAddressService;

test('checkout addresses default to shipping and ignore unrelated input', function (): void {
    $addresses = (new CheckoutAddressService)->prepare([
        'recipient_name' => 'Buyer',
        'address_line_one' => '10 Main Road',
        'city' => 'Colombo',
        'phone' => '0771234567',
        'billing_recipient_name' => 'Old billing name',
        'admin' => 'yes',
    ]);

    expect($addresses)->toBe([
        'shipping_address' => [
            'recipient_name' => 'Buyer',
            'address_line_one' => '10 Main Road',
            'address_line_two' => null,
            'city' => 'Colombo',
            'postal_code' => null,
            'phone' => '0771234567',
        ],
        'billing_address' => null,
    ]);
});

test('checkout prepares a separate billing address without changing delivery', function (): void {
    $addresses = (new CheckoutAddressService)->prepare([
        'recipient_name' => 'Recipient',
        'city' => 'Colombo',
        'billing_address' => 'different',
        'billing_recipient_name' => 'Accounts Department',
        'billing_city' => 'Kandy',
        'billing_phone' => '0811234567',
    ]);

    expect($addresses['shipping_address']['recipient_name'])->toBe('Recipient')
        ->and($addresses['shipping_address']['city'])->toBe('Colombo')
        ->and($addresses['billing_address']['recipient_name'])->toBe('Accounts Department')
        ->and($addresses['billing_address']['city'])->toBe('Kandy')
        ->and($addresses['billing_address']['phone'])->toBe('0811234567')
        ->and($addresses['billing_address']['address_line_two'])->toBeNull();
});
