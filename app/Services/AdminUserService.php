<?php

namespace App\Services;

use App\Contracts\Repositories\AdminUserRepository;
use App\Models\User;

class AdminUserService
{
    public function __construct(private readonly AdminUserRepository $users) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        return [
            'users' => $this->users->paginateForAdmin($filters)
                ->through(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_types' => AdminUserExportService::accountTypes($user),
                    'roles' => $user->roles->pluck('label')->sort()->values()->all(),
                    'is_active' => (bool) $user->is_active,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'seller_profile' => $user->sellerProfile === null ? null : [
                        'store_name' => $user->sellerProfile->store_name,
                        'seller_type' => $user->sellerProfile->seller_type,
                        'status' => $user->sellerProfile->status,
                    ],
                ]),
            'filters' => $filters,
        ];
    }
}
