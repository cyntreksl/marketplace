<?php

namespace App\Contracts\Repositories;

use Closure;

interface SeoMonitoringRepository
{
    /** @return array<string, mixed>|null */
    public function get(string $key): ?array;

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value): void;

    public function synchronized(string $key, Closure $callback): mixed;
}
