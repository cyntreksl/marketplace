<?php

namespace App\Http\Requests;

use App\Services\AdminProductExportService;
use Illuminate\Validation\Rule;

class AdminProductExportRequest extends AdminListingIndexRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(AdminProductExportService::columnKeys())],
        ];
    }

    /** @return list<string> */
    public function columns(): array
    {
        return array_values($this->validated('columns'));
    }
}
