<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerPaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['sometimes', Rule::in(['all', 'pending', 'successful', 'failed'])]];
    }

    public function status(): string
    {
        return (string) $this->validated('status', 'all');
    }
}
