<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface AdminUserRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, User>
     */
    public function lazyForAdminExport(array $filters): LazyCollection;
}
