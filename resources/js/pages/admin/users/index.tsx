import { Form, Head, Link } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import {
    downloadExport,
    index,
} from '@/actions/App/Http/Controllers/AdminUserController';
import { AdminExportDialog } from '@/components/admin-export-dialog';
import type { ExportColumnOption } from '@/components/admin-export-dialog';
import { AdminPagination } from '@/components/admin-pagination';
import { PortalLayout } from '@/components/portal-layout';
import type { PaginationLink } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    account_types: string[];
    roles: string[];
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    seller_profile: {
        store_name: string;
        seller_type: string;
        status: string;
    } | null;
};

type Filters = {
    search: string;
    account_type: string;
    active: string;
    verification: string;
    created_from: string;
    created_to: string;
    sort: string;
};

type UserPaginator = {
    data: AdminUser[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

export default function AdminUsersIndex({
    users,
    filters,
    exportColumns,
}: {
    users: UserPaginator;
    filters: Filters;
    exportColumns: ExportColumnOption[];
}) {
    const hasActiveFilters =
        filters.search !== '' ||
        filters.account_type !== 'all' ||
        filters.active !== 'all' ||
        filters.verification !== 'all' ||
        filters.created_from !== '' ||
        filters.created_to !== '' ||
        filters.sort !== 'newest';

    return (
        <PortalLayout portal="admin" title="All users">
            <Head title="All users" />
            <div className="space-y-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                            Marketplace operations
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight">
                            All users
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Browse administrators, sellers, and buyers across
                            the marketplace.
                        </p>
                    </div>
                    <AdminExportDialog
                        action={downloadExport.url()}
                        filters={filters}
                        columns={exportColumns}
                        title="Export users to Excel"
                    />
                </header>

                <Form
                    {...index.form()}
                    options={{ preserveState: true, replace: true }}
                    className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <label className="relative sm:col-span-2 xl:col-span-1">
                        <span className="sr-only">Search users</span>
                        <Search className="absolute top-3.5 left-3 size-4 text-slate-400" />
                        <input
                            name="search"
                            defaultValue={filters.search}
                            maxLength={100}
                            placeholder="Name, email, or store"
                            className="min-h-11 w-full rounded-xl border bg-transparent pr-3 pl-9 text-sm"
                        />
                    </label>
                    <select
                        name="account_type"
                        defaultValue={filters.account_type}
                        aria-label="Account type"
                        className="min-h-11 rounded-xl border bg-transparent px-3 text-sm"
                    >
                        <option value="all">All account types</option>
                        <option value="admin">Administrators</option>
                        <option value="seller">Sellers</option>
                        <option value="buyer">Buyers</option>
                    </select>
                    <select
                        name="active"
                        defaultValue={filters.active}
                        aria-label="Account status"
                        className="min-h-11 rounded-xl border bg-transparent px-3 text-sm"
                    >
                        <option value="all">All account statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select
                        name="verification"
                        defaultValue={filters.verification}
                        aria-label="Email verification"
                        className="min-h-11 rounded-xl border bg-transparent px-3 text-sm"
                    >
                        <option value="all">All verification statuses</option>
                        <option value="verified">Email verified</option>
                        <option value="unverified">Email unverified</option>
                    </select>
                    <label className="grid gap-1 text-xs font-semibold text-muted-foreground">
                        Registered from
                        <input
                            type="date"
                            name="created_from"
                            defaultValue={filters.created_from}
                            className="min-h-11 rounded-xl border bg-transparent px-3 text-sm text-foreground"
                        />
                    </label>
                    <label className="grid gap-1 text-xs font-semibold text-muted-foreground">
                        Registered to
                        <input
                            type="date"
                            name="created_to"
                            defaultValue={filters.created_to}
                            className="min-h-11 rounded-xl border bg-transparent px-3 text-sm text-foreground"
                        />
                    </label>
                    <select
                        name="sort"
                        defaultValue={filters.sort}
                        aria-label="Sort users"
                        className="min-h-11 rounded-xl border bg-transparent px-3 text-sm"
                    >
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="name">Name A–Z</option>
                    </select>
                    <button className="min-h-11 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground">
                        Apply
                    </button>
                    {hasActiveFilters && (
                        <Link
                            href={index()}
                            className="text-center text-sm font-semibold text-primary sm:col-span-2 xl:col-span-4"
                        >
                            Clear filters
                        </Link>
                    )}
                </Form>

                <p className="text-sm text-muted-foreground">
                    {users.total.toLocaleString()} user
                    {users.total === 1 ? '' : 's'}
                </p>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    {users.data.length === 0 ? (
                        <div className="grid place-items-center gap-2 p-12 text-center">
                            <Users className="size-8 text-slate-400" />
                            <p className="font-bold">No users found</p>
                            <p className="text-sm text-slate-500">
                                Try changing the account filters.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800">
                            {users.data.map((user) => (
                                <article
                                    key={user.id}
                                    className="grid gap-4 p-5 md:grid-cols-[1.25fr_1fr_1fr_auto] md:items-center"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-black">
                                            {user.name}
                                        </p>
                                        <p className="text-sm break-all text-slate-500">
                                            {user.email}
                                        </p>
                                        {user.seller_profile && (
                                            <p className="mt-1 text-xs text-slate-500">
                                                {user.seller_profile.store_name}{' '}
                                                ·{' '}
                                                {label(
                                                    user.seller_profile
                                                        .seller_type,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {user.account_types.map(
                                            (accountType) => (
                                                <span
                                                    key={accountType}
                                                    className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary"
                                                >
                                                    {accountType}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                    <div className="text-sm">
                                        <p className="font-semibold">
                                            {user.is_active
                                                ? 'Active account'
                                                : 'Inactive account'}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {user.email_verified_at
                                                ? 'Email verified'
                                                : 'Email unverified'}
                                            {user.seller_profile &&
                                                ` · Seller ${label(user.seller_profile.status)}`}
                                        </p>
                                    </div>
                                    <p className="text-xs text-slate-500 md:text-right">
                                        Registered
                                        <span className="mt-1 block text-sm font-semibold text-foreground">
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleString()}
                                        </span>
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}
                    <AdminPagination paginator={users} />
                </section>
            </div>
        </PortalLayout>
    );
}
