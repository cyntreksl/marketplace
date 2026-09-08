<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminListingIndexRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['all', 'draft', 'pending_review', 'approved', 'changes_requested', 'rejected', 'suspended', 'archived'])],
            'listing_type' => ['nullable', Rule::in(['all', 'buy_now', 'auction'])],
            'product_type' => ['nullable', Rule::in(['all', 'simple', 'variant'])],
            'condition' => ['nullable', Rule::in(['all', 'new', 'used', 'refurbished'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'title'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{search: string, status: string, listing_type: string, product_type: string, condition: string, sort: string} */
    public function filters(string $defaultStatus): array
    {
        return [
            'search' => Str::squish((string) $this->validated('search', '')),
            'status' => (string) ($this->validated('status') ?: $defaultStatus),
            'listing_type' => (string) ($this->validated('listing_type') ?: 'all'),
            'product_type' => (string) ($this->validated('product_type') ?: 'all'),
            'condition' => (string) ($this->validated('condition') ?: 'all'),
            'sort' => (string) ($this->validated('sort') ?: 'newest'),
        ];
    }
}
