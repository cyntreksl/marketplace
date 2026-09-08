<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuctionSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->roles()->whereIn('name', ['admin', 'super_admin'])->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'types' => ['required', 'array:normal,blind,time_extended'],
            'types.normal' => ['required', 'boolean'],
            'types.blind' => ['required', 'boolean'],
            'types.time_extended' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'types' => [
                'normal' => $this->boolean('types.normal'),
                'blind' => $this->boolean('types.blind'),
                'time_extended' => $this->boolean('types.time_extended'),
            ],
        ]);
    }
}
