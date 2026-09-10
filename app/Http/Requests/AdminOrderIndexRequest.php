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
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{search: string, status: string, sort: string} */
    public function filters(): array
    {
        return [
            'search' => Str::squish((string) $this->validated('search', '')),
            'status' => (string) ($this->validated('status') ?: 'all'),
            'sort' => (string) ($this->validated('sort') ?: 'newest'),
        ];
    }
}
