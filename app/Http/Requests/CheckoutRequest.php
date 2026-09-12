<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $addressRules = [
            'recipient_name' => ['required', 'string', 'max:120'],
            'address_line_one' => ['required', 'string', 'max:255'],
            'address_line_two' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{9}$/'],
        ];

        $rules = $addressRules;
        $rules['email'] = [Rule::requiredIf($this->user() === null), 'nullable', 'email:rfc', 'max:255'];
        $rules['marketing_opt_in'] = ['sometimes', 'boolean'];
        $rules['billing_address'] = ['sometimes', 'required', 'in:shipping,different'];
        $rules['save_shipping_address'] = [Rule::excludeIf($this->user() === null), 'sometimes', 'boolean'];
        $rules['shipping_address_label'] = [Rule::excludeIf($this->user() === null), 'required_if:save_shipping_address,1', 'nullable', 'string', 'max:80'];
        $rules['save_shipping_for_billing'] = [Rule::excludeIf($this->user() === null), 'sometimes', 'boolean'];
        $rules['save_billing_address'] = [Rule::excludeIf($this->user() === null), 'sometimes', 'boolean'];
        $rules['billing_address_label'] = [Rule::excludeIf($this->user() === null), 'required_if:save_billing_address,1', 'nullable', 'string', 'max:80'];

        foreach ($addressRules as $field => $fieldRules) {
            $rules['billing_'.$field] = ['exclude_unless:billing_address,different', ...$fieldRules];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a 10-digit phone number starting with 0.',
            'billing_phone.regex' => 'Enter a 10-digit billing phone number starting with 0.',
        ];
    }
}
