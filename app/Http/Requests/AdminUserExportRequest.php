<?php

namespace App\Http\Requests;

use App\Services\AdminUserExportService;
use Illuminate\Validation\Rule;

class AdminUserExportRequest extends AdminUserIndexRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(AdminUserExportService::columnKeys())],
        ];
    }

    /** @return list<string> */
    public function columns(): array
    {
        return array_values($this->validated('columns'));
    }
}
