<?php

namespace App\Http\Requests;

use App\Services\AdminOrderExportService;
use Illuminate\Validation\Rule;

class AdminOrderExportRequest extends AdminOrderIndexRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(AdminOrderExportService::columnKeys())],
        ];
    }

    /** @return list<string> */
    public function columns(): array
    {
        return array_values($this->validated('columns'));
    }
}
