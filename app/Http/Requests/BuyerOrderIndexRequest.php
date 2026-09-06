<?php

namespace App\Http\Requests;

use App\BuyerOrderStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['stage' => ['sometimes', Rule::enum(BuyerOrderStage::class)]];
    }

    public function stage(): BuyerOrderStage
    {
        return BuyerOrderStage::tryFrom((string) $this->validated('stage', 'all')) ?? BuyerOrderStage::All;
    }
}
