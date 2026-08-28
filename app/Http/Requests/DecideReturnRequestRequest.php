<?php

namespace App\Http\Requests;

use App\Models\ReturnRequest;
use App\ReturnStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideReturnRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $returnRequest = $this->route('returnRequest');

        return $returnRequest instanceof ReturnRequest
            && ($this->user()?->can('decide', $returnRequest) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([ReturnStatus::Approved->value, ReturnStatus::Rejected->value])],
            'response_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
