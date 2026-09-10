<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CancelSellerOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->routeIs('admin.orders.packages.cancel')) {
            return $this->user()?->roles()->whereIn('name', [Role::Admin, Role::SuperAdmin])->exists() === true;
        }

        return $this->user()?->can('cancel', $this->route('sellerOrder')) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => Str::squish((string) $this->input('reason'))]);
    }
}
