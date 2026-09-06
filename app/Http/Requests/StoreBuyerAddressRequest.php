<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBuyerAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_enabled' => $this->boolean('shipping_enabled'),
            'billing_enabled' => $this->boolean('billing_enabled'),
            'is_default_shipping' => $this->boolean('is_default_shipping'),
            'is_default_billing' => $this->boolean('is_default_billing'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'address_line_one' => ['required', 'string', 'max:255'],
            'address_line_two' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{9}$/'],
            'shipping_enabled' => ['required', 'boolean'],
            'billing_enabled' => ['required', 'boolean'],
            'is_default_shipping' => ['sometimes', 'boolean'],
            'is_default_billing' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('shipping_enabled') && ! $this->boolean('billing_enabled')) {
                $validator->errors()->add('shipping_enabled', 'Choose shipping, billing, or both.');
            }

            if ($this->boolean('is_default_shipping') && ! $this->boolean('shipping_enabled')) {
                $validator->errors()->add('is_default_shipping', 'A default shipping address must be enabled for shipping.');
            }

            if ($this->boolean('is_default_billing') && ! $this->boolean('billing_enabled')) {
                $validator->errors()->add('is_default_billing', 'A default billing address must be enabled for billing.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a 10-digit phone number starting with 0.'];
    }
}
