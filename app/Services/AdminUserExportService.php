<?php

namespace App\Services;

use App\Contracts\Repositories\AdminUserRepository;
use App\Models\Role;
use App\Models\User;

class AdminUserExportService
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'user_id' => 'User ID',
        'name' => 'Name',
        'email' => 'Email',
        'account_types' => 'Account types',
        'roles' => 'Exact roles',
        'active' => 'Active',
        'email_verified_at' => 'Email verified date',
        'store_name' => 'Store name',
        'seller_type' => 'Seller type',
        'seller_status' => 'Seller status',
        'seller_phone' => 'Seller phone',
        'created_at' => 'Registered date',
        'updated_at' => 'Updated date',
    ];

    /** @var list<string> */
    private const DEFAULT_COLUMNS = [
        'user_id', 'name', 'email', 'account_types', 'active', 'email_verified_at', 'created_at',
    ];

    public function __construct(
        private readonly AdminUserRepository $users,
        private readonly AdminXlsxWriter $writer,
    ) {}

    /** @return list<string> */
    public static function columnKeys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /** @return list<array{key: string, label: string, defaultSelected: bool}> */
    public static function columnOptions(): array
    {
        $options = [];

        foreach (self::COLUMNS as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label, 'defaultSelected' => in_array($key, self::DEFAULT_COLUMNS, true)];
        }

        return $options;
    }

    /** @param array<string, mixed> $filters
     * @param  list<string>  $columns
     */
    public function createTemporaryFile(array $filters, array $columns): string
    {
        $headers = array_map(fn (string $column): string => self::COLUMNS[$column], $columns);

        return $this->writer->createTemporaryFile('Users', $headers, $this->rows($filters, $columns));
    }

    /** @param array<string, mixed> $filters
     * @param  list<string>  $columns
     * @return \Generator<int, list<mixed>>
     */
    private function rows(array $filters, array $columns): \Generator
    {
        foreach ($this->users->lazyForAdminExport($filters) as $user) {
            yield array_map(fn (string $column): mixed => $this->value($user, $column), $columns);
        }
    }

    private function value(User $user, string $column): mixed
    {
        return match ($column) {
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_types' => implode('; ', self::accountTypes($user)),
            'roles' => $user->roles->pluck('name')->sort()->implode('; '),
            'active' => (bool) $user->is_active,
            'email_verified_at' => $user->email_verified_at,
            'store_name' => $user->sellerProfile?->store_name,
            'seller_type' => $user->sellerProfile?->seller_type,
            'seller_status' => $user->sellerProfile?->status,
            'seller_phone' => $user->sellerProfile?->phone,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            default => null,
        };
    }

    /** @return list<string> */
    public static function accountTypes(User $user): array
    {
        $roleNames = $user->roles->pluck('name');
        $types = [];

        if ($roleNames->intersect([Role::Admin, Role::FinanceAdmin, Role::SuperAdmin])->isNotEmpty()) {
            $types[] = 'Admin';
        }
        if ($roleNames->intersect([Role::IndividualSeller, Role::BusinessSeller])->isNotEmpty()) {
            $types[] = 'Seller';
        }
        if ($roleNames->contains(Role::Buyer)) {
            $types[] = 'Buyer';
        }

        return $types === [] ? ['Unassigned'] : $types;
    }
}
