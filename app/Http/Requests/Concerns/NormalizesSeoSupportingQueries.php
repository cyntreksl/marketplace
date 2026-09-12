<?php

namespace App\Http\Requests\Concerns;

trait NormalizesSeoSupportingQueries
{
    /** @return array<int, string> */
    protected function normalizeSeoSupportingQueries(mixed $value): array
    {
        $queries = is_array($value) ? $value : preg_split('/\R/u', (string) $value);

        return collect($queries ?: [])
            ->map(fn (mixed $query): string => trim((string) $query))
            ->filter()
            ->values()
            ->all();
    }
}
