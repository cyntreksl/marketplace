<?php

namespace App\Http\Requests;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateListingModerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'changes_requested', 'rejected', 'suspended', 'archived'])],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $listing = $this->route('listing');

            if ($this->input('status') === 'approved' && (! $listing instanceof Listing || $listing->status !== 'pending_review')) {
                $validator->errors()->add('status', 'Only a submitted listing can be approved.');
            }
        }];
    }
}
