<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
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
            'account_type' => ['nullable', Rule::in(['all', 'admin', 'seller', 'buyer'])],
            'active' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'verification' => ['nullable', Rule::in(['all', 'verified', 'unverified'])],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{search: string, account_type: string, active: string, verification: string, created_from: string, created_to: string, sort: string} */
    public function filters(): array
    {
        return [
            'search' => Str::squish((string) $this->validated('search', '')),
            'account_type' => (string) ($this->validated('account_type') ?: 'all'),
            'active' => (string) ($this->validated('active') ?: 'all'),
            'verification' => (string) ($this->validated('verification') ?: 'all'),
            'created_from' => (string) ($this->validated('created_from') ?: ''),
            'created_to' => (string) ($this->validated('created_to') ?: ''),
            'sort' => (string) ($this->validated('sort') ?: 'newest'),
        ];
    }
}
