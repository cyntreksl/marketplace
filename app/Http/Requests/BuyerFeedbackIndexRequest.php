<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerFeedbackIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['view' => ['sometimes', Rule::in(['awaiting', 'submitted'])]];
    }

    public function view(): string
    {
        return (string) $this->validated('view', 'awaiting');
    }
}
