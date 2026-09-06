<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?(?=(?:[^0-9]*[0-9]){7,15}[^0-9]*$)[0-9 ()-]+$/'],
        ];

        $rules = $addressRules;
        $rules['billing_address'] = ['sometimes', 'required', 'in:shipping,different'];

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
            'phone.regex' => 'Enter a valid phone number with 7 to 15 digits and an optional country code. Letters are not allowed.',
            'billing_phone.regex' => 'Enter a valid billing phone number with 7 to 15 digits and an optional country code. Letters are not allowed.',
        ];
    }
}
