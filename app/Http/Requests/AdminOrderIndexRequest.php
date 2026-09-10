<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminOrderIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->roles()->whereIn('name', [Role::Admin, Role::SuperAdmin])->exists() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'pending_payment', 'confirmed', 'cancelled', 'expired'])],
            'payment_method' => ['nullable', Rule::in(['all', 'stripe', 'cod', 'bank_transfer'])],
            'payment_status' => ['nullable', Rule::in(['all', 'pending', 'pending_collection', 'paid', 'expired', 'cancelled', 'partially_refunded', 'refunded'])],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{search: string, status: string, payment_method: string, payment_status: string, created_from: string, created_to: string, sort: string} */
    public function filters(): array
    {
        return [
            'search' => Str::squish((string) $this->validated('search', '')),
            'status' => (string) ($this->validated('status') ?: 'all'),
            'payment_method' => (string) ($this->validated('payment_method') ?: 'all'),
            'payment_status' => (string) ($this->validated('payment_status') ?: 'all'),
            'created_from' => (string) ($this->validated('created_from') ?: ''),
            'created_to' => (string) ($this->validated('created_to') ?: ''),
            'sort' => (string) ($this->validated('sort') ?: 'newest'),
        ];
    }
}
