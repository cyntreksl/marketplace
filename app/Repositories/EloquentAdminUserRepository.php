<?php

namespace App\Repositories;

use App\Contracts\Repositories\AdminUserRepository;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

class EloquentAdminUserRepository implements AdminUserRepository
{
    public function paginateForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->adminQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function lazyForAdminExport(array $filters): LazyCollection
    {
        return $this->adminQuery($filters)->lazy(500);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<User>
     */
    private function adminQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $query = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'is_active', 'created_at', 'updated_at'])
            ->with([
                'roles:id,name,label',
                'sellerProfile:id,user_id,store_name,seller_type,status,phone',
            ])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('sellerProfile', fn (Builder $seller): Builder => $seller->where('store_name', 'like', "%{$search}%"));
            }))
            ->when(($filters['account_type'] ?? 'all') !== 'all', function (Builder $query) use ($filters): void {
                $roles = match ($filters['account_type']) {
                    'admin' => [Role::Admin, Role::FinanceAdmin, Role::SuperAdmin],
                    'seller' => [Role::IndividualSeller, Role::BusinessSeller],
                    'buyer' => [Role::Buyer],
                    default => [],
                };

                $query->whereHas('roles', fn (Builder $rolesQuery): Builder => $rolesQuery->whereIn('name', $roles));
            })
            ->when(($filters['active'] ?? 'all') !== 'all', fn (Builder $query): Builder => $query->where('is_active', $filters['active'] === 'active'))
            ->when(($filters['verification'] ?? 'all') === 'verified', fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'))
            ->when(($filters['verification'] ?? 'all') === 'unverified', fn (Builder $query): Builder => $query->whereNull('email_verified_at'))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', $date.' 23:59:59'));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $query;
    }
}
