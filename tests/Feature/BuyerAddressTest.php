<?php

use App\Models\BuyerAddress;
use App\Models\CustomerOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function buyerAddressPayload(array $overrides = []): array
{
    return [
        'label' => 'Home',
        'recipient_name' => 'Nimal Perera',
        'address_line_one' => '10 Galle Road',
        'address_line_two' => 'Colpetty',
        'city' => 'Colombo',
        'postal_code' => '00300',
        'phone' => '0771234567',
        'shipping_enabled' => true,
        'billing_enabled' => false,
        ...$overrides,
    ];
}

test('verified buyers can manage dual-purpose addresses and independent defaults', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('buyer.addresses.store'), buyerAddressPayload([
        'billing_enabled' => true,
    ]))->assertRedirect();

    $first = BuyerAddress::query()->sole();
    expect($first->is_default_shipping)->toBeTrue()
        ->and($first->is_default_billing)->toBeTrue();

    $second = BuyerAddress::factory()->for($buyer, 'buyer')->create([
        'shipping_enabled' => true,
        'billing_enabled' => true,
        'is_default_shipping' => false,
        'is_default_billing' => false,
    ]);

    $this->actingAs($buyer)->patch(route('buyer.addresses.default', $second), [
        'purpose' => 'shipping',
    ])->assertRedirect();

    expect($first->refresh()->is_default_shipping)->toBeFalse()
        ->and($first->is_default_billing)->toBeTrue()
        ->and($second->refresh()->is_default_shipping)->toBeTrue();

    $this->actingAs($buyer)->delete(route('buyer.addresses.destroy', $second))->assertRedirect();

    expect(BuyerAddress::query()->find($second->id))->toBeNull()
        ->and(BuyerAddress::query()->where('buyer_id', $buyer->id)->where('is_default_shipping', true)->exists())->toBeFalse();
});

test('address validation and ownership isolation are enforced', function () {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $address = BuyerAddress::factory()->for($buyer, 'buyer')->create();

    $this->actingAs($buyer)->post(route('buyer.addresses.store'), buyerAddressPayload([
        'shipping_enabled' => false,
        'billing_enabled' => false,
        'phone' => '123',
    ]))->assertSessionHasErrors(['shipping_enabled', 'phone']);

    $this->actingAs($otherBuyer)->patch(route('buyer.addresses.update', $address), buyerAddressPayload())
        ->assertForbidden();
    $this->actingAs($otherBuyer)->delete(route('buyer.addresses.destroy', $address))
        ->assertForbidden();
});

test('checkout prefers an in-progress session then falls back to purpose defaults', function () {
    $buyer = User::factory()->create();
    BuyerAddress::factory()->for($buyer, 'buyer')->create([
        'label' => 'Default delivery',
        'recipient_name' => 'Default Recipient',
        'shipping_enabled' => true,
        'is_default_shipping' => true,
    ]);

    $this->actingAs($buyer)->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shippingAddress.recipient_name', 'Default Recipient')
            ->has('savedAddresses', 1));

    $this->withSession([
        'checkout.shipping_address' => buyerAddressPayload(['recipient_name' => 'Session Recipient']),
    ])->actingAs($buyer)->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shippingAddress.recipient_name', 'Session Recipient'));
});

test('checkout can explicitly save entered details as a new address', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('checkout.store'), buyerAddressPayload([
        'billing_address' => 'shipping',
        'save_shipping_address' => true,
        'shipping_address_label' => 'Parents home',
        'save_shipping_for_billing' => true,
    ]))->assertRedirect(route('checkout.payment.show'));

    $this->assertDatabaseHas('buyer_addresses', [
        'buyer_id' => $buyer->id,
        'label' => 'Parents home',
        'shipping_enabled' => true,
        'billing_enabled' => true,
        'is_default_shipping' => true,
        'is_default_billing' => true,
    ]);
});

test('editing a saved address never mutates an existing order snapshot', function () {
    $buyer = User::factory()->create();
    $address = BuyerAddress::factory()->for($buyer, 'buyer')->create([
        'address_line_one' => 'Original Lane',
    ]);
    $order = CustomerOrder::factory()->create([
        'buyer_id' => $buyer->id,
        'shipping_address' => $address->snapshot(),
        'billing_address' => $address->snapshot(),
    ]);

    $this->actingAs($buyer)->patch(route('buyer.addresses.update', $address), buyerAddressPayload([
        'address_line_one' => 'New Lane',
    ]))->assertRedirect();

    expect($order->refresh()->shipping_address['address_line_one'])->toBe('Original Lane')
        ->and($order->billing_address['address_line_one'])->toBe('Original Lane');
});
