<?php

namespace App\Services;

class CheckoutAddressService
{
    /**
     * @param  array<string, string|null>  $data
     * @return array{shipping_address: array<string, string|null>, billing_address: array<string, string|null>|null}
     */
    public function prepare(array $data): array
    {
        return [
            'shipping_address' => $this->address($data),
            'billing_address' => ($data['billing_address'] ?? 'shipping') === 'different'
                ? $this->address($data, 'billing_')
                : null,
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string|null>
     */
    private function address(array $data, string $prefix = ''): array
    {
        $address = [];

        foreach (['recipient_name', 'address_line_one', 'address_line_two', 'city', 'postal_code', 'phone'] as $field) {
            $address[$field] = $data[$prefix.$field] ?? null;
        }

        return $address;
    }
}
